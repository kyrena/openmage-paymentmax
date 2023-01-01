<?php
/**
 * Created J/02/12/2021
 * Updated M/04/01/2022
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

class Kyrena_Paymentmax_Model_Wrapper_Tpayblik extends \tpayLibs\src\_class_tpay\PaymentBlik {

	public function __construct($merchantSecurityCode = null, $merchantId = null, $trApiKey = null, $trApiPass = null) {
		$this->merchantSecret = $merchantSecurityCode;
		$this->merchantId = (int) $merchantId;
		$this->trApiKey   = $trApiKey;
		$this->trApiPass  = $trApiPass;
		parent::__construct();
	}

	public function requests($url, $params) {
		$params['api_password'] = $this->trApiPass;
		return (new \tpayLibs\src\_class_tpay\Curl\Curl())
			->setVerifyHost(2)
			->enableVerifyPeer()
			->disableVerbose()
			->setRequestUrl($url)
			->setPostData($params)
			->enableJSONResponse()
			->doRequest()
			->getResult();
	}
}