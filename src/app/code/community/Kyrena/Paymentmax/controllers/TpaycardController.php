<?php
/**
 * Created M/11/01/2022
 * Updated M/11/01/2022
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

require_once(Mage::getModuleDir('controllers', 'Kyrena_Paymentmax').'/PaymentmaxController.php');

class Kyrena_Paymentmax_TpaycardController extends Kyrena_Paymentmax_PaymentmaxController {
	protected $_code = 'paymentmax_tpaycard';
}