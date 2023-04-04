<?php
/**
 * Created V/22/10/2021
 * Updated M/07/03/2023
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

class Kyrena_Paymentmax_Helper_Rewrite_Payment extends Mage_Payment_Helper_Data {

	public function getStoreMethods($store = null, $quote = null) {

		if (!is_object($quote) && (PHP_SAPI != 'cli'))
			$quote = Mage::getSingleton('checkout/session')->getQuote();

		$hasQuote = false;
		if (is_object($quote)) {
			$hasQuote = true;
			$shipping = $quote->getIsVirtual() ? null : $quote->getShippingAddress()->getShippingMethod();
			$group    = $quote->getData('customer_group_id');
		}

		$methods = parent::getStoreMethods($store, $quote);
		$codes   = [];

		foreach ($methods as $method)
			$codes[] = $method->getCode();

		foreach ($methods as $idx => $method) {

			if (!empty($shipping)) {

				$keys = array_filter(explode(',', Mage::getStoreConfig('payment/'.$method->getCode().'/show_when_shipping')));
				if (!empty($keys)) {
					$hide = true;
					foreach ($keys as $key) {
						if (str_contains($shipping, $key))
							$hide = false;
					}
					if ($hide) {
						unset($methods[$idx]);
						continue;
					}
				}

				$keys = array_filter(explode(',', Mage::getStoreConfig('payment/'.$method->getCode().'/hide_when_shipping')));
				if (!empty($keys)) {
					$hide = false;
					foreach ($keys as $key) {
						if (str_contains($shipping, $key))
							$hide = true;
					}
					if ($hide) {
						unset($methods[$idx]);
						continue;
					}
				}
			}
			else if ($hasQuote) {

				$keys = Mage::getStoreConfig('payment/'.$method->getCode().'/show_when_shipping');
				if (!empty($keys)) {
					unset($methods[$idx]);
					continue;
				}
			}

			$keys = array_filter(explode(',', Mage::getStoreConfig('payment/'.$method->getCode().'/hide_when_payment')));
			if (!empty($keys)) {
				foreach ($keys as $key) {
					if (in_array($key, $codes)) {
						unset($methods[$idx]);
						continue 2;
					}
				}
			}

			$keys = array_filter(explode(',', Mage::getStoreConfig('payment/'.$method->getCode().'/show_for_customer_group')));
			if (!empty($keys) && (empty($group) || !in_array($group, $keys))) {
				unset($methods[$idx]);
				//continue;
			}
		}

		return $methods;
	}
}