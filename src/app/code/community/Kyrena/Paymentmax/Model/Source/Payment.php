<?php
/**
 * Created V/06/11/2020
 * Updated J/20/10/2022
 *
 * Copyright 2021-2023 | Fabrice Creuzot <fabrice~cellublue~com>
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

class Kyrena_Paymentmax_Model_Source_Payment {

	protected $_options;

	public function toOptionArray() {

		if (empty($this->_options)) {

			$this->_options = [];

			$storeId = $this->getStoreId();
			$codes   = array_keys(Mage::getConfig()->getNode('default/payment')->asArray());

			foreach ($codes as $code) {
				$text = Mage::getStoreConfig('payment/'.$code.'/title', $storeId);
				$this->_options[$code] = ['value' => $code, 'label' => empty($text) ? $code : $code.' / '.$text];
			}

			ksort($this->_options);
		}

		return $this->_options;
	}

	protected function getStoreId() {

		$store   = Mage::app()->getRequest()->getParam('store');
		$website = Mage::app()->getRequest()->getParam('website');

		if (!empty($store))
			$storeId = Mage::app()->getStore($store)->getId();
		else if (!empty($website))
			$storeId = Mage::getModel('core/website')->load($website)->getDefaultStore()->getId();
		else
			$storeId = Mage::app()->getDefaultStoreView()->getId();

		return $storeId;
	}
}