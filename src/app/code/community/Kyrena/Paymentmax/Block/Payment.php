<?php
/**
 * Created V/22/10/2021
 * Updated J/13/01/2022
 *
 * Copyright 2021-2022 | Fabrice Creuzot <fabrice~cellublue~com>
 * Copyright 2021-2022 | Jérôme Siau <jerome~cellublue~com>
 * https://github.com/kyrena/openmage-paymentmax
 *
 * This program is free software, you can redistribute it or modify
 * it under the terms of the GNU General Public License (GPL) as published
 * by the free software foundation, either version 2 of the license, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but without any warranty, without even the implied warranty of
 * merchantability or fitness for a particular purpose. See the
 * GNU General Public License (GPL) for more details.
 */

abstract class Kyrena_Paymentmax_Block_Payment extends Mage_Payment_Block_Form {

	protected function _construct() {
		parent::_construct();
		$this->setTemplate('kyrena/paymentmax/'.str_replace('paymentmax_', '', $this->_code).'.phtml');
	}

	public function getText() {
		$text = Mage::getStoreConfig('payment/'.$this->getMethodCode().'/description');
		return empty($text) ? $this->__('You will be redirected to payment page after submitting order.') : $text;
	}
}