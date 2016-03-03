<?php

/**
 * 
 * @author geiser
 *
 */
class Flopstore_Custom_Block_Sales_Order_View extends Mage_Adminhtml_Block_Sales_Order_View {
	
	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see Mage_Adminhtml_Block_Widget_Form_Container::_prepareLayout()
	 */
	protected function _prepareLayout() {
		$order = $this->getOrder ();
		if ($this->_isAllowedAction ( 'invoice' ) && $order->canInvoice () && $this->_isAllowedAction ( 'ship' ) && $order->canShip () && ! $order->getForcedDoShipmentWithInvoice ()) {
			
			$this->_addButton ( 'order_invoice_ship', array (
					'label' => Mage::helper ( 'sales' )->__ ( 'Invoice / Ship / NF-e' ),
					'onclick' => 'setLocation(\'' . $this->getUrl ( '*/flopstore_custom/invoiceShip' ) . '\')',
					'class' => 'go' 
			) );
		}
		
		return parent::_prepareLayout ();
	}
}