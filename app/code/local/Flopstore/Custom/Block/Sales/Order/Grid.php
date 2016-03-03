<?php
/**
 * 
 * @author geiser
 *
 */
class Flopstore_Custom_Block_Sales_Order_Grid extends Mage_Adminhtml_Block_Sales_Order_Grid {
	protected function _prepareMassaction() {
		parent::_prepareMassaction ();
		
		// Append new mass action option
		$this->getMassactionBlock ()->addItem ( 'mass_invoice_ship', array (
				'label' => $this->__ ( 'Invoice/Ship/NF-e' ),
				'url' => $this->getUrl ( '*/flopstore_custom/massInvoiceShip' ) 
		) );
		
		$this->getMassactionBlock ()->addItem ( 'mass_show_print_links', array (
				'label' => $this->__ ( 'Generate Prints' ),
				'url' => $this->getUrl ( '*/flopstore_custom/showPrintLinks' ) 
		) );
	}
}