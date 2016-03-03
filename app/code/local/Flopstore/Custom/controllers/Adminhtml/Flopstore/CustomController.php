<?php
/**
 * 
 * @author geiser
 *
 */
class Flopstore_Custom_Adminhtml_Flopstore_CustomController extends Mage_Adminhtml_Controller_Action {
	
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
	 */
	public function massInvoiceShipAction() {
		$orderIds = $this->getRequest ()->getParam ( 'order_ids' );
		if (! is_array ( $orderIds )) {
			$this->_getSession ()->addError ( $this->__ ( 'Please select orders(s).' ) );
		} else {
			if (! empty ( $orderIds )) {
				try {
					$count = 0;
					foreach ( $orderIds as $orderId ) {
						$invoice = $this->_initInvoice ( $orderId );
						if ($this->_processInvoiceShip ( $invoice )) {
							$count ++;
						}
						$order = Mage::getModel ( 'sales/order' )->load ( $orderId );
						$this->_getSession ()->addNotice ( $this->__ ( 'Order %d, please <a href="%s" target="_blank">click to print</a> (or click for <a href="%s" target="_blank">Invoice</a>, <a href="%s" target="_blank">Shipping Label</a>, <a href="%s" target="_blank">NF-e</a>)', $order->getIncrementId (), $this->getUrl ( '*/flopstore_custom/pdfSingle', array (
								'order_id' => $orderId 
						) ), $this->getUrl ( '*/flopstore_custom/pdfInvoice', array (
								'order_id' => $orderId 
						) ), $this->getUrl ( '*/flopstore_custom/pdfShippingLabel', array (
								'order_id' => $orderId 
						) ), $this->getUrl ( '*/flopstore_custom/pdfNfe', array (
								'order_id' => $orderId 
						) ) ) );
					}
					if ($count > 0) {
						$this->_getSession ()->addSuccess ( $this->__ ( 'Total of %d record(s) have been invoiced and shipped.', $count ) );
					} else {
						$this->_getSession ()->addSuccess ( $this->__ ( 'No records invoiced and shipped.' ) );
					}
				} catch ( Exception $e ) {
					$this->_getSession ()->addError ( $e->getMessage () );
				}
			}
		}
		$this->_redirect ( '*/sales_order/index' );
	}
	public function testAction() {
		$orderIds = $this->getRequest ()->getParam ( 'order_ids' );
		if (! is_array ( $orderIds )) {
			$this->_getSession ()->addError ( $this->__ ( 'Please select orders(s).' ) );
		} else {
			if (! empty ( $orderIds )) {
				try {
					foreach ( $orderIds as $orderId ) {
						$order = Mage::getModel ( 'sales/order' )->load ( $orderId );
						$order->setShippingMethod ( 'dhlint_P' );
						$order->save ();
					}
				} catch ( Exception $e ) {
					$this->_getSession ()->addError ( $e->getMessage () );
				}
			}
		}
		$this->_redirect ( '*/sales_order/index' );
	}
	public function getAllShippingMethods() {
		$methods = Mage::getSingleton ( 'shipping/config' )->getActiveCarriers ();
		
		$options = array ();
		
		foreach ( $methods as $_ccode => $_carrier ) {
			$_methodOptions = array ();
			if ($_methods = $_carrier->getAllowedMethods ()) {
				foreach ( $_methods as $_mcode => $_method ) {
					$_code = $_ccode . '_' . $_mcode;
					$_methodOptions [] = array (
							'value' => $_code,
							'label' => $_method 
					);
				}
				
				if (! $_title = Mage::getStoreConfig ( "carriers/$_ccode/title" ))
					$_title = $_ccode;
				
				$options [] = array (
						'value' => $_methodOptions,
						'label' => $_title 
				);
			}
		}
		
		return $options;
	}
	
	/**
	 */
	public function showPrintLinksAction() {
		$orderIds = $this->getRequest ()->getParam ( 'order_ids' );
		if (! is_array ( $orderIds )) {
			$this->_getSession ()->addError ( $this->__ ( 'Please select orders(s).' ) );
		} else {
			if (! empty ( $orderIds )) {
				try {
					foreach ( $orderIds as $orderId ) {
						$order = Mage::getModel ( 'sales/order' )->load ( $orderId );
						$this->_getSession ()->addNotice ( $this->__ ( 'Order %d, please <a href="%s" target="_blank">click to print</a> (or click for <a href="%s" target="_blank">Invoice</a>, <a href="%s" target="_blank">Shipping Label</a>, <a href="%s" target="_blank">NF-e</a>)', $order->getIncrementId (), $this->getUrl ( '*/flopstore_custom/pdfSingle', array (
								'order_id' => $orderId 
						) ), $this->getUrl ( '*/flopstore_custom/pdfInvoice', array (
								'order_id' => $orderId 
						) ), $this->getUrl ( '*/flopstore_custom/pdfShippingLabel', array (
								'order_id' => $orderId 
						) ), $this->getUrl ( '*/flopstore_custom/pdfNfe', array (
								'order_id' => $orderId 
						) ) ) );
					}
				} catch ( Exception $e ) {
					$this->_getSession ()->addError ( $e->getMessage () );
				}
			}
		}
		$this->_redirect ( '*/sales_order/index' );
	}
	
	/**
	 */
	public function printSinglePdfAction() {
		$orderIds = $this->getRequest ()->getParam ( 'order_ids' );
		if (! is_array ( $orderIds )) {
			$this->_getSession ()->addError ( $this->__ ( 'Please select orders(s).' ) );
		} else {
			if (! empty ( $orderIds )) {
				try {
					foreach ( $orderIds as $orderId ) {
						$pdf2show = new Zend_Pdf ();
						if ($invoicePdf = $this->_prepareInvoicePdf ( $orderId )) {
							$pdf = Zend_Pdf::parse ( $invoicePdf, 1 );
							foreach ( $pdf->pages as $page ) {
								$template = clone $page;
								$page2add = new Zend_Pdf_Page ( $template );
								$pdf2show->pages [] = $page2add;
							}
						}
						if ($labelPdf = $this->_prepareShippingLabelPdf ( $orderId )) {
							$pdf = Zend_Pdf::parse ( $labelPdf, 1 );
							foreach ( $pdf->pages as $page ) {
								$template = clone $page;
								$page2add = new Zend_Pdf_Page ( $template );
								$pdf2show->pages [] = $page2add;
							}
						}
						$this->_prepareDownloadResponse ( $fileName, $pdf2show->render (), 'application/pdf' );
					}
				} catch ( Exception $e ) {
					$this->_getSession ()->addError ( $e->getMessage () );
				}
			}
		}
		$this->_redirect ( '*/sales_order/index' );
	}
	
	/**
	 */
	public function pdfSingleAction() {
		$request = $this->getRequest ();
		$orderId = $request->getParam ( 'order_id' );
		if ($outputPdf = $this->_prepareInvoicePdf ( $orderId )) {
			$fileName = 'InvoiceShipmentLabel.pdf';
			$pdf2show = new Zend_Pdf ();
			if ($labelPdf = $this->_prepareShippingLabelPdf ( $orderId )) {
				
				$label = clone $labelPdf->pages [0];
				$page2addLabel = new Zend_Pdf_Page ( $label );
				
				$doc = clone $labelPdf->pages [1];
				$page2addDoc = new Zend_Pdf_Page ( $doc );
				
				$pdf2show->pages [0] = $page2addLabel;
				$pdf2show->pages [4] = $page2addDoc;
				$pdf2show->pages [5] = $page2addDoc;
				$pdf2show->pages [6] = $page2addDoc;
			}
			if ($invoicePdf = $this->_prepareInvoicePdf ( $orderId )) {
				foreach ( $invoicePdf->pages as $page ) {
					$template1 = clone $page;
					$template2 = clone $page;
					$template3 = clone $page;
					$pdf2show->pages [1] = $template1;
					$pdf2show->pages [2] = $template2;
					$pdf2show->pages [3] = $template3;
				}
			}
			ksort ( $pdf2show->pages );
			$this->_prepareDownloadResponse ( $fileName, $pdf2show->render (), 'application/pdf' );
			$this->getResponse ()->setHeader ( 'Content-Disposition', 'inline; filename="' . $fileName . '"', true );
			return;
		}
		$this->getResponse ()->clearBody ();
		$this->getResponse ()->clearHeaders ();
		$this->getResponse ()->setHttpResponseCode ( 400 );
		$this->getResponse ()->setBody ( $this->__ ( "Shipping label not found for order %d.", $orderId ) );
	}
	
	/**
	 */
	public function pdfInvoiceAction() {
		$request = $this->getRequest ();
		$orderId = $request->getParam ( 'order_id' );
		if ($outputPdf = $this->_prepareInvoicePdf ( $orderId )) {
			$fileName = 'Invoice.pdf';
			$this->_prepareDownloadResponse ( $fileName, $outputPdf->render (), 'application/pdf' );
			$this->getResponse ()->setHeader ( 'Content-Disposition', 'inline; filename="' . $fileName . '"', true );
			return;
		}
		$this->getResponse ()->clearBody ();
		$this->getResponse ()->clearHeaders ();
		$this->getResponse ()->setHttpResponseCode ( 400 );
		$this->getResponse ()->setBody ( $this->__ ( "Shipping label not found for order %d.", $orderId ) );
	}
	
	/**
	 */
	public function pdfShippingLabelAction() {
		$request = $this->getRequest ();
		$orderId = $request->getParam ( 'order_id' );
		if ($outputPdf = $this->_prepareShippingLabelPdf ( $orderId )) {
			$fileName = 'ShippingLabels.pdf';
			$this->_prepareDownloadResponse ( $fileName, $outputPdf->render (), 'application/pdf' );
			$this->getResponse ()->setHeader ( 'Content-Disposition', 'inline; filename="' . $fileName . '"', true );
			return;
		}
		$this->getResponse ()->clearBody ();
		$this->getResponse ()->clearHeaders ();
		$this->getResponse ()->setHttpResponseCode ( 400 );
		$this->getResponse ()->setBody ( $this->__ ( "Shipping label not found for order %d.", $orderId ) );
	}
	
	/**
	 */
	public function pdfNfeAction() {
		$this->_getSession ()->addError ( Mage::helper ( 'sales' )->__ ( 'Sorry, this functionality is not working yet.' ) );
		$this->_redirect ( '*/sales_order/index' );
	}
	
	/**
	 * Save invoice
	 * We can save only new invoice.
	 * Existing invoices are not editable
	 */
	public function invoiceShipAction() {
		$data = $this->getRequest ()->getPost ( 'invoice' );
		$orderId = $this->getRequest ()->getParam ( 'order_id' );
		try {
			$invoice = $this->_initInvoice ( $orderId );
			if ($invoice) {
				$this->_processInvoiceShip ( $invoice );
				$this->_getSession ()->addSuccess ( $this->__ ( 'The invoice and shipment have been created.' ) );
				Mage::getSingleton ( 'adminhtml/session' )->getCommentText ( true );
				$this->_redirect ( '*/sales_order/view', array (
						'order_id' => $orderId 
				) );
			} else {
				$this->_redirect ( '*/*/new', array (
						'order_id' => $orderId 
				) );
			}
			return;
		} catch ( Mage_Core_Exception $e ) {
			$this->_getSession ()->addError ( $e->getMessage () );
		} catch ( Exception $e ) {
			$this->_getSession ()->addError ( $this->__ ( 'Unable to save the invoice.' ) );
			Mage::logException ( $e );
		}
		$this->_redirect ( '*/sales_order/view', array (
				'order_id' => $orderId 
		) );
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
	 * @param unknown $invoice        	
	 */
	protected function _processInvoiceShip($invoice) {
		if (! $invoice) {
			return false;
		}
		
		$invoice->register ();
		$invoice->getOrder ()->setIsInProcess ( true );
		
		$method = Mage::getStoreConfig ( "custom/shipping/method" );
		if ($method && $invoice->getOrder ()->getShippingMethod () != $method) {
			$oldMethod = $invoice->getOrder ()->getShippingMethod ();
			$allMethods = Mage::getSingleton ( 'custom/system_config_source_shippingmethods' )->toArray ();
			if (! $description = $allMethods [$method]) {
				$description = $method;
			}
			if (! $oldDescription = $allMethods [$oldMethod]) {
				$oldDescription = $oldMethod;
			}
			$invoice->getOrder ()->setShippingMethod ( $method );
			$invoice->getOrder ()->setShippingDescription ( $description );
			$invoice->getOrder ()->addStatusHistoryComment ( "Order shipping method changed from '$oldDescription' to '$description'" );
		}
		
		$transactionSave = Mage::getModel ( 'core/resource_transaction' );
		$transactionSave->addObject ( $invoice );
		$transactionSave->addObject ( $invoice->getOrder () );
		$transactionSave->save ();
		
		$shipment = $this->_initShipment ( $invoice );
		$shipment->setEmailSent ( $invoice->getEmailSent () );
		$shipment = $this->_preparePackage ( $shipment );
		$this->_createShippingLabel ( $shipment );
		
		$transactionSave = Mage::getModel ( 'core/resource_transaction' );
		$transactionSave->addObject ( $shipment );
		$transactionSave->addObject ( $shipment->getOrder () );
		$transactionSave->save ();
		
		/*
		 * $order = $shipment->getOrder ();
		 * $this->_setOrderState ( $order, Mage_Sales_Model_Order::STATE_COMPLETE );
		 * $order->setStatus ( Mage_Sales_Model_Order::STATE_COMPLETE );
		 * $order->setActionFlag ( Mage_Sales_Model_Order::ACTION_FLAG_SHIP, false );
		 * $order->save ();
		 */
		
		// send invoice/shipment emails
		$comment = '';
		try {
			// $invoice->sendEmail ( true, $comment );
		} catch ( Exception $e ) {
			Mage::logException ( $e );
			$this->_getSession ()->addError ( $this->__ ( 'Unable to send the invoice email.' ) );
		}
		if ($shipment) {
			try {
				// $shipment->sendEmail ( true );
			} catch ( Exception $e ) {
				Mage::logException ( $e );
				$this->_getSession ()->addError ( $this->__ ( 'Unable to send the shipment email.' ) );
			}
		}
		
		return true;
	}
	
	/**
	 * Initialize invoice model instance
	 *
	 * @return Mage_Sales_Model_Order_Invoice
	 */
	protected function _initInvoice($orderId) {
		$invoice = false;
		$itemsToInvoice = 0;
		if ($orderId) {
			$order = Mage::getModel ( 'sales/order' )->load ( $orderId );
			/**
			 * Check order existing
			 */
			if (! $order->getId ()) {
				$this->_getSession ()->addError ( $this->__ ( 'The order no longer exists.' ) );
				return false;
			}
			/**
			 * Check invoice create availabilityget
			 */
			if (! $order->canInvoice ()) {
				$this->_getSession ()->addError ( $this->__ ( 'The order does not allow creating an invoice.' ) );
				return false;
			}
			
			$invoice = Mage::getModel ( 'sales/service_order', $order )->prepareInvoice ( $this->_countOrderItems ( $order ) );
			if (! $invoice->getTotalQty ()) {
				Mage::throwException ( $this->__ ( 'Cannot create an invoice without products.' ) );
			}
		}
		
		Mage::register ( 'current_invoice', $invoice );
		return $invoice;
	}
	
	/**
	 * Initialize shipment model instance
	 *
	 * @return Mage_Sales_Model_Order_Shipment|bool
	 */
	protected function _initShipment(Mage_Sales_Model_Order_Invoice $invoice) {
		$shipment = false;
		$order = $invoice->getOrder ();
		
		/**
		 * Check order existing
		 */
		if (! $order->getId ()) {
			$this->_getSession ()->addError ( $this->__ ( 'The order no longer exists.' ) );
			return false;
		}
		/**
		 * Check shipment is available to create separate from invoice
		 */
		if ($order->getForcedDoShipmentWithInvoice ()) {
			$this->_getSession ()->addError ( $this->__ ( 'Cannot do shipment for the order separately from invoice.' ) );
			return false;
		}
		/**
		 * Check shipment create availability
		 */
		if (! $order->canShip ()) {
			$this->_getSession ()->addError ( $this->__ ( 'Cannot do shipment for the order.' ) );
			return false;
		}
		
		// $shipment = Mage::getModel ( 'sales/service_order', $order )->prepareShipment ();
		$shipment = $order->prepareShipment ();
		$shipment->register ();
		$shipment->getOrder ()->setIsInProcess ( true );
		Mage::register ( 'current_shipment', $shipment );
		
		return $shipment;
	}
	
	/**
	 *
	 * @param Mage_Sales_Model_Order_Shipment $shipment        	
	 * @return number
	 */
	protected function _countPackageItems(Mage_Sales_Model_Order_Shipment $shipment) {
		$totalItems = 0;
		foreach ( $shipment->getPackages () as $package ) {
			$totalItems += count ( $package ['items'] );
		}
		return $totalItems;
	}
	
	/**
	 *
	 * @param Mage_Sales_Model_Order $order        	
	 * @return number
	 */
	protected function _countOrderItems(Mage_Sales_Model_Order $order) {
		$qtys = array ();
		foreach ( $order->getAllItems () as $orderItem ) {
			$qtys [$orderItem->getId ()] = $orderItem->getQtyOrdered ();
		}
		return $qtys;
	}
	
	/**
	 *
	 * @param Mage_Sales_Model_Order_Shipment $shipment        	
	 */
	protected function _preparePackage(Mage_Sales_Model_Order_Shipment $shipment) {
		$items = $shipment->getAllItems ();
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
		return $shipment;
	}
	
	/**
	 *
	 * @param Mage_Sales_Model_Order_Shipment $shipment        	
	 */
	protected function _createShippingLabel(Mage_Sales_Model_Order_Shipment $shipment) {
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
	
	/**
	 * Combine array of labels as instance PDF
	 *
	 * @param array $labelsContent        	
	 * @return Zend_Pdf
	 */
	protected function _combineLabelsPdf(array $labelsContent) {
		$outputPdf = new Zend_Pdf ();
		foreach ( $labelsContent as $content ) {
			if (stripos ( $content, '%PDF-' ) !== false) {
				$pdfLabel = Zend_Pdf::parse ( $content );
				foreach ( $pdfLabel->pages as $page ) {
					$outputPdf->pages [] = clone $page;
				}
			} else {
				$page = $this->_createPdfPageFromImageString ( $content );
				if ($page) {
					$outputPdf->pages [] = $page;
				}
			}
		}
		return $outputPdf;
	}
	
	/**
	 *
	 * @param unknown $order        	
	 * @param unknown $newOrderStatus        	
	 */
	protected function _setOrderState($order, $newOrderStatus) {
		$statuses = Mage::getModel ( 'sales/order_config' )->getStates ();
		foreach ( $statuses as $state => $label ) {
			foreach ( Mage::getModel ( 'sales/order_config' )->getStateStatuses ( $state, false ) as $status ) {
				if ($status == $newOrderStatus) {
					$order->setData ( 'state', $state );
					return;
				}
			}
		}
	}
}