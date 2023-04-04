<?php
/**
 * Created V/24/03/2023
 * Updated V/24/03/2023
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

class Kyrena_Paymentmax_Model_Payment_Paypalcheckout extends Kyrena_Paymentmax_Model_Payment_Paypal {

	protected $_code = 'paymentmax_paypalcheckout';

	public function isAvailable($quote = null) {
		return false;
	}
}