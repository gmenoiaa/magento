<?php
class Flopstore_Custom_Model_System_Config_Source_Shippingmethods {
	
	/**
	 * Options getter
	 *
	 * @return array
	 */
	public function toOptionArray() {
		$methods = $this->_getAvailableShippingMethods ();
		$options = array ();
		$options[''] = "No Updates";
		foreach ( $methods as $_code => $_value ) {
			$options [] = array (
					'value' => $_code,
					'label' => "$_value" 
			);
		}
		return $options;
	}
	
	/**
	 * Get options in "key-value" format
	 *
	 * @return array
	 */
	public function toArray() {
		return $this->_getAvailableShippingMethods ();
	}
	
	/**
	 * Retrieve an array with available shipping methods
	 */
	protected function _getAvailableShippingMethods() {
		$methods = Mage::getSingleton ( 'shipping/config' )->getActiveCarriers ();
		$options = array ();
		foreach ( $methods as $_ccode => $_carrier ) {
			
			if (! $_title = Mage::getStoreConfig ( "carriers/$_ccode/title" ))
				$_title = $_ccode;
			
			if ($_methods = $_carrier->getAllowedMethods ()) {
				foreach ( $_methods as $_mcode => $_method ) {
					$_code = $_ccode . '_' . $_mcode;
					$options [$_code] = "$_title - $_method";
				}
			}
		}
		return $options;
	}
}