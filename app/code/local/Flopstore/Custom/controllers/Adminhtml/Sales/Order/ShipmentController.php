<?php
require_once (Mage::getModuleDir ( 'controllers', 'Mage_Adminhtml' ) . DS . 'Sales' . DS . 'Order' . DS . 'ShipmentController.php');
class Flopstore_Custom_Adminhtml_Sales_Order_ShipmentController extends Mage_Adminhtml_Sales_Order_ShipmentController {
	
	/**
	 * Dimentional units
	 */
	const DEFAULT_WEIGHT_UNITS = 'KG';
	const DEFAULT_DIMENSION_UNITS = 'CM';
	
	/**
	 * Package dimensions
	 */
	const DEFAULT_PACKAGE_HEIGHT = 6;
	const DEFAULT_PACKAGE_WIDTH = 10;
	const DEFAULT_PACKAGE_LENGTH = 27;
	
	/**
	 * Create shipping label action for specific shipment
	 */
	public function createLabelCustomAction() {
		$response = new Varien_Object ();
		try {
			$shipment = $this->_initShipment ();
			$this->_preparePackage ( $shipment );
			if ($this->_createShippingLabelCustom ( $shipment )) {
				$shipment->save ();
				$this->_getSession ()->addSuccess ( Mage::helper ( 'sales' )->__ ( 'The shipping label has been created.' ) );
				$response->setOk ( true );
			}
		} catch ( Mage_Core_Exception $e ) {
			$response->setError ( true );
			$response->setMessage ( $e->getMessage () );
		} catch ( Exception $e ) {
			Mage::logException ( $e );
			$response->setError ( true );
			$response->setMessage ( Mage::helper ( 'sales' )->__ ( 'An error occurred while creating shipping label.' ) );
		}
		
		$this->getResponse ()->setBody ( $response->toJson () );
	}
	
	/**
	 * Prints a label by rendering in browser
	 *
	 * @return Flopstore_Custom_Adminhtml_Sales_Order_ShipmentController
	 */
	public function printLabelCustomAction() {
		try {
			$shipment = $this->_initShipment ();
			$labelContent = $shipment->getShippingLabel ();
			if ($labelContent) {
				$pdfContent = null;
				if (stripos ( $labelContent, '%PDF-' ) !== false) {
					$pdfContent = $labelContent;
				} else {
					$pdf = new Zend_Pdf ();
					$page = $this->_createPdfPageFromImageString ( $labelContent );
					if (! $page) {
						$this->_getSession ()->addError ( Mage::helper ( 'sales' )->__ ( 'File extension not known or unsupported type in the following shipment: %s', $shipment->getIncrementId () ) );
					}
					$pdf->pages [] = $page;
					$pdfContent = $pdf->render ();
				}
				$filename = 'ShippingLabel(' . $shipment->getIncrementId () . ').pdf';
				$this->_prepareDownloadResponse ( $filename, $pdfContent, 'application/pdf' );
				$this->getResponse ()->setHeader ( 'Content-Disposition', 'inline; filename="' . $filename . '"', true );
				return $this;
			}
		} catch ( Mage_Core_Exception $e ) {
			$this->_getSession ()->addError ( $e->getMessage () );
		} catch ( Exception $e ) {
			Mage::logException ( $e );
			$this->_getSession ()->addError ( Mage::helper ( 'sales' )->__ ( 'An error occurred while creating shipping label.' ) );
		}
		$this->_redirect ( '*/sales_order_shipment/view', array (
				'shipment_id' => $this->getRequest ()->getParam ( 'shipment_id' ) 
		) );
	}
	
	/**
	 * Batch print shipping labels for whole shipments.
	 * Push pdf document with shipping labels to user browser
	 *
	 * @return null
	 */
	public function massPrintAllCustomAction() {
		$request = $this->getRequest ();
		$ids = $request->getParam ( 'order_ids' );
		$pages = array ();
		foreach ( $ids as $orderId ) {
			if ($invoicePdf = $this->_prepareInvoicePdf ( $orderId )) {
				$pages [] = $invoicePdf;
			}
			if ($labelPdf = $this->_prepareShippingLabelPdf ( $orderId )) {
				$pages [] = $orderId;
			}
			// TODO nfe
		}
		if (! empty ( $pages )) {
			$outputPdf = $this->_combinePdfs ( $pages );
			$this->_prepareDownloadResponse ( 'ShippingLabels.pdf', $outputPdf->render (), 'application/pdf' );
			return;
		}
		$this->_getSession ()->addError ( Mage::helper ( 'sales' )->__ ( 'There are no pages to print related to selected orders.' ) );
		$this->_redirect ( '*/sales_order/index' );
	}
	/**
	 * Combine array of labels as instance PDF
	 *
	 * @param array $labelsContent        	
	 * @return Zend_Pdf
	 */
	protected function _combinePdfs(array $pages) {
		$outputPdf = new Zend_Pdf ();
		foreach ( $pages as $content ) {
			$pdfLabel = Zend_Pdf::parse ( $content );
			foreach ( $pdfLabel->pages as $page ) {
				$outputPdf->pages [] = clone $page;
			}
		}
		return $outputPdf;
	}
	
	/**
	 *
	 * @param unknown $orderId        	
	 */
	protected function _prepareShippingLabelPdf($orderId) {
		$shipments = Mage::getResourceModel ( 'sales/order_shipment_collection' )->setOrderFilter ( $orderId );
		if ($shipments && $shipments->getSize ()) {
			foreach ( $shipments as $shipment ) {
				$labelContent = $shipment->getShippingLabel ();
				if ($labelContent) {
					$labelsContent [] = $labelContent;
				}
			}
		}
		if (! empty ( $labelsContent )) {
			$outputPdf = $this->_combineLabelsPdf ( $labelsContent );
			return $outputPdf;
		}
		return false;
	}
	
	/**
	 *
	 * @param unknown $orderId        	
	 */
	protected function _prepareInvoicePdf($orderId) {
		$invoices = Mage::getResourceModel ( 'sales/order_invoice_collection' )->setOrderFilter ( $orderId )->load ();
		if ($invoices->getSize () > 0) {
			$pdf = Mage::getModel ( 'sales/order_pdf_invoice' )->getPdf ( $invoices );
			return $pdf;
		}
		return false;
	}
	
	/**
	 *
	 * @param Mage_Sales_Model_Order_Shipment $shipment        	
	 */
	protected function _preparePackage(Mage_Sales_Model_Order_Shipment $shipment) {
		$items = $this->getLayout ()->createBlock ( 'adminhtml/sales_order_shipment_packaging_grid' )->getCollection ();
		$totalWeight = 0;
		$customsValue = 0;
		$itemsList = array ();
		foreach ( $items as $item ) {
			$totalWeight += $item->getWeight ();
			$customsValue += $item->getPrice ();
			$itemsList [$item->getOrderItemId ()] = array (
					'qty' => $item->getQty,
					'customs_value' => $item->getPrice (),
					'price' => $item->getPrice (),
					'name' => $item->getName (),
					'weight' => $item->getWeight (),
					'product_id' => $item->getProductId (),
					'order_item_id' => $item->getOrderItemId () 
			);
		}
		$data = array (
				'params' => array (
						'container' => Mage_Usa_Model_Shipping_Carrier_Dhl_International::DHL_CONTENT_TYPE_NON_DOC,
						'weight' => $totalWeight,
						'customs_value' => $customsValue,
						'length' => self::DEFAULT_PACKAGE_LENGTH,
						'width' => self::DEFAULT_PACKAGE_WIDTH,
						'height' => self::DEFAULT_PACKAGE_HEIGHT,
						'weight_units' => self::DEFAULT_WEIGHT_UNITS,
						'dimension_units' => self::DEFAULT_DIMENSION_UNITS 
				),
				'items' => $itemsList 
		);
		$shipment->setPackages ( array (
				$data 
		) );
		$this->_createShippingLabelCustom ( $shipment );
		return $shipment;
	}
	
	/**
	 *
	 * @param Mage_Sales_Model_Order_Shipment $shipment        	
	 */
	protected function _createShippingLabelCustom(Mage_Sales_Model_Order_Shipment $shipment) {
		if (! $shipment) {
			return false;
		}
		$carrier = $shipment->getOrder ()->getShippingCarrier ();
		if (! $carrier->isShippingLabelsAvailable ()) {
			return false;
		}
		
		$response = Mage::getModel ( 'shipping/shipping' )->requestToShipment ( $shipment );
		if ($response->hasErrors ()) {
			Mage::throwException ( $response->getErrors () );
		}
		if (! $response->hasInfo ()) {
			return false;
		}
		$labelsContent = array ();
		$trackingNumbers = array ();
		$info = $response->getInfo ();
		foreach ( $info as $inf ) {
			if (! empty ( $inf ['tracking_number'] ) && ! empty ( $inf ['label_content'] )) {
				$labelsContent [] = $inf ['label_content'];
				$trackingNumbers [] = $inf ['tracking_number'];
			}
		}
		$outputPdf = $this->_combineLabelsPdf ( $labelsContent );
		$shipment->setShippingLabel ( $outputPdf->render () );
		$carrierCode = $carrier->getCarrierCode ();
		$carrierTitle = Mage::getStoreConfig ( 'carriers/' . $carrierCode . '/title', $shipment->getStoreId () );
		if ($trackingNumbers) {
			foreach ( $trackingNumbers as $trackingNumber ) {
				$track = Mage::getModel ( 'sales/order_shipment_track' )->setNumber ( $trackingNumber )->setCarrierCode ( $carrierCode )->setTitle ( $carrierTitle );
				$shipment->addTrack ( $track );
			}
		}
		return true;
	}
}
