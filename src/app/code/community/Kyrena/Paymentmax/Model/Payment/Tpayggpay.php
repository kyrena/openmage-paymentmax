<?php
/**
 * Created M/24/05/2022
 * Updated M/31/05/2022
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

class Kyrena_Paymentmax_Model_Payment_Tpayggpay extends Kyrena_Paymentmax_Model_Payment_Tpay {

	protected $_code = 'paymentmax_tpayggpay';

	public function isAvailable($quote = null) {

		$result = parent::isAvailable($quote);

		if ($result && Mage::helper('core')->isModuleEnabled('Luigifab_Apijs')) {
			$browser = Mage::getSingleton('apijs/useragentparser')->parse();
			$result  = (!empty($browser['browser']) && ($browser['browser'] == 'Chrome')) || (!empty($browser['platform']) && ($browser['platform'] == 'Android'));
		}

		return $result;
	}
}