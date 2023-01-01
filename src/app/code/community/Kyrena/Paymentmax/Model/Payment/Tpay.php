<?php
/**
 * Created V/12/11/2021
 * Updated W/23/11/2022
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

class Kyrena_Paymentmax_Model_Payment_Tpay extends Kyrena_Paymentmax_Model_Payment {

	protected $_code          = 'paymentmax_tpay';
	protected $_formBlockType = 'paymentmax/payment_tpay';
	protected $_codes  = ['paymentmax_tpay', 'paymentmax_tpayblik', 'paymentmax_tpaycard', 'paymentmax_tpayggpay', 'tpay', 'tpayCards'];
	protected $_allcnf = ['paymentmax_tpay', 'paymentmax_tpayblik', 'paymentmax_tpaycard', 'paymentmax_tpayggpay']; // allowed configuration
	protected $_cache  = []; // transactions list


	// openmage
	public function getCode() {
		return in_array($this->_code, $this->_allcnf) ? $this->_code : 'paymentmax_tpay';
	}

	public function assignData($data) {

		if ($this->_code != 'paymentmax_tpayblik')
			return parent::assignData($data);

		if (!($data instanceof Varien_Object))
			$data = new Varien_Object($data);

		$this->getInfoInstance()->setData('po_number', implode('', $data->getData('paymentmax_tpayblik_otp')));
		return $this;
	}


	// kyrena
	protected function callApi(string $type, string $method, array $params, int $storeId = 0) {

		\tpayLibs\src\_class_tpay\Utilities\Util::$loggingEnabled = false;

		if ($type == 'old') {
			$client = new Kyrena_Paymentmax_Model_Wrapper_Tpayold(
				Mage::helper('core')->decrypt($this->getConfigData('api_security', $storeId)),
				$this->getConfigData('api_merchant', $storeId),
				Mage::helper('core')->decrypt($this->getConfigData('api_username', $storeId)),
				Mage::helper('core')->decrypt($this->getConfigData('api_password', $storeId))
			);
		}
		else if ($type == 'payment') {
			$client = new Kyrena_Paymentmax_Model_Wrapper_Tpaypayment(
				Mage::helper('core')->decrypt($this->getConfigData('api_security', $storeId)),
				$this->getConfigData('api_merchant', $storeId),
				Mage::helper('core')->decrypt($this->getConfigData('api_username', $storeId)),
				Mage::helper('core')->decrypt($this->getConfigData('api_password', $storeId))
			);
		}
		else if ($type == 'blik') {
			$client = new Kyrena_Paymentmax_Model_Wrapper_Tpayblik(
				Mage::helper('core')->decrypt($this->getConfigData('api_security', $storeId)),
				$this->getConfigData('api_merchant', $storeId),
				Mage::helper('core')->decrypt($this->getConfigData('api_username', $storeId)),
				Mage::helper('core')->decrypt($this->getConfigData('api_password', $storeId))
			);
		}
		else if ($type == 'ipn') {
			$client = new Kyrena_Paymentmax_Model_Wrapper_Tpayipn(
				Mage::helper('core')->decrypt($this->getConfigData('api_security')),
				$this->getConfigData('api_merchant')
			);
		}
		else if ($type == 'refund') {
			$client = new Kyrena_Paymentmax_Model_Wrapper_Tpayrefund(
				Mage::helper('core')->decrypt($this->getConfigData('api_security', $storeId)),
				$this->getConfigData('api_merchant', $storeId),
				Mage::helper('core')->decrypt($this->getConfigData('api_username', $storeId)),
				Mage::helper('core')->decrypt($this->getConfigData('api_password', $storeId))
			);
		}
		else if ($type == 'report') {
			$client = new Kyrena_Paymentmax_Model_Wrapper_Tpayreport(
				Mage::helper('core')->decrypt($this->getConfigData('api_security', $storeId)),
				$this->getConfigData('api_merchant', $storeId),
				Mage::helper('core')->decrypt($this->getConfigData('api_username', $storeId)),
				Mage::helper('core')->decrypt($this->getConfigData('api_password', $storeId))
			);
		}

		// sentry
		$_SERVER['debug_tpay_type']    = get_class($client);
		$_SERVER['debug_tpay_method']  = $method;
		$_SERVER['debug_tpay_request'] = $params;

		try {
			// en 2 temps pour
			// Unexpected response from tpay server 0 in file vendor/tpay-com/tpay-php/tpayLibs/src/_class_tpay/Curl/Curl.php
			$response = $client->{$method}(...$params);
		}
		catch (Throwable $t) {
			sleep(3);
			$response = $client->{$method}(...$params);
		}

		// sentry
		$_SERVER['debug_tpay_response'] = $response;

		//echo '<pre>',print_r($_POST, true),print_r($data, true),print_r($response, true);exit;
		return $response;
	}

	protected function askRefund(object $order, string $captureId, float $amount, bool $partial) {

		$storeId = $order->getStoreId();

		// https://docs.tpay.com/#!/Transaction_API/post_api_gw_api_key_chargeback_any
		$response = $partial ?
			$this->callApi('refund', 'refundAny', [$captureId, $amount], $storeId) :
			$this->callApi('refund', 'refund', [$captureId], $storeId);

		$codes = \tpayLibs\src\Dictionaries\ErrorCodes\TransactionApiErrors::ERROR_CODES;
		$error = empty($response['err']) ? 'incorrect' : $response['err'];
		$error = (!empty($error) && array_key_exists($error, $codes)) ? $error.' '.$codes[$error] : $error;

		return [
			'status'      => ($response['result'] == 1) ? 'success' : $error,
			'refundId'    => $captureId.'-refund-'.time(),
			'amount'      => $amount,
			'currency'    => 'PLN',
			'raw_details' => [
				'amount_value'    => $amount,
				'amount_currency' => 'PLN',
				'is_test'         => 'no',
			],
		];
	}

	protected function askRedirect(object $order) {

		$storeId = $order->getStoreId();
		$blik    = ($this->_code == 'paymentmax_tpayblik') ? $order->getPayment()->getData('po_number') : null;
		$billing = $order->getBillingAddress();

		$city = trim($billing->getData('city'));
		// strlen in tpayLibs\src\_class_tpay\Validators\FieldsValidator
		if (strlen($city) > 32) {
			$city = trim(mb_substr($city, 0, 32));
			while (strlen($city) > 32)
				$city = trim(mb_substr($city, 0, -1));
		}

		// https://docs.tpay.com/#!/Transaction_API/post_api_gw_api_key_transaction_create
		// https://secure.tpay.com/groups-{idtpay}0.js
		//                                                                        tpay : tpaycard,tpayggpay : tpayblik
		$response = $this->callApi(empty($blik) ? (($this->_code == 'paymentmax_tpay') ? 'old' : 'payment') : 'blik', 'create', [[
			'amount'      => $order->getTotalDue(),
			'description' => (string) $order->getData('increment_id'),
			'crc'         => (string) $order->getId(),
			'result_url'  => $this->getOrderIpnUrl(['id' => $order->getData('increment_id')]),
			'return_url'  => $this->getOrderReturnUrl(),
			'return_error_url' => $this->getOrderReturnUrl(['error' => 1]),
			'email'       => $order->getData('customer_email'),
			'name'        => $order->getData('customer_firstname').' '.$order->getData('customer_lastname'),
			'city'        => $city,
			'zip'         => $billing->getData('postcode'),
			'country'     => $billing->getData('country_id'),
			'group'       => ($this->_code == 'paymentmax_tpayggpay') ? 166 : (empty($blik) ? 103 : 150), // tpayggpay : tpay,tpaycard : tpayblik
			'accept_tos'  => empty($blik) ? 0 : 1, // tpay,tpaycard,tpayggpay : tpayblik
			'merchant_description' => Mage::getStoreConfig('general/store_information/name', $storeId), //.' - test'
		]], $storeId);

		if (empty($blik)) {
			// tpay - the very old and shameful way
			if ($this->_code == 'paymentmax_tpay')
				return str_replace('</body>', '<p>'.str_replace('.', '...', Mage::helper('sales')->__('You will be redirected to the payment system website.')).'</p></body>', '<style type="text/css">body { background:#F2F2F5; cursor:wait; display:flex; align-items:center; height:85vh; justify-content:center; overflow-y:scroll; } form { display:none; }</style>'.strip_tags($response, '<script> <form> <body> <input>'));
			// tpayblik,tpaycard,tpayggpay
			else
				$url = empty($response['url']) ? 'https://secure.tpay.com/?gtitle='.$response['title'] : $response['url'];
		}
		else {
			// tpayblik
			$order->addStatusHistoryComment('Customer blik code used: '.$blik)->save();
			$blik = $this->callApi('blik', 'blik', [$response['title'], $blik], $storeId);
			$url  = $this->getOrderWaitingUrl();
			$order->getPayment()->setData('cc_secure_verify', (!empty($blik['result']) && ($blik['result'] == 1)) ? 'valid' : 'invalid');
		}

		$order->getPayment()->setData('last_trans_id', $response['title'])->save();
		$order->addStatusHistoryComment('Customer redirected to: '.$url)->save();

		return $url;
	}


	// retourne les données de la transaction
	// demande via le numéro de transaction enregistré par redirectToPayment, sinon fait une recherche par date
	// si on fait une recherche retourne false si la commande n'est pas payée, sinon retourne l'id de la transaction et son statut
	public function askTransaction(bool $search = false) {

		$order     = $this->getInfoInstance()->getOrder();
		$payment   = $order->getPayment();
		$captureId = $payment->lookupTransaction(null, Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE);
		$captureId = (empty($captureId) || empty($captureId->getTxnId())) ? $payment->getData('last_trans_id') : $captureId->getTxnId();

		// TR-123-1234567, TR-123-1234567-xyz-1234567890
		if (substr_count($captureId, '-') > 2)
			$captureId = implode('-', array_slice(explode('-', $captureId), 0, 3));

		// https://docs.tpay.com/#!/Transaction_API/post_api_gw_api_key_transaction_report
		// attention, en cas de remboursement la transaction d'origine n'est pas modifié dans le rapport
		if ($search && empty($captureId)) {

			$dates = [strtotime($order->getData('created_at'))];
			foreach ($order->getCreditmemosCollection() as $refund)
				$dates[] = strtotime($refund->getData('created_at'));

			$dates = array_reverse($dates);
			foreach ($dates as $time) {

				$ckey = date('Ymd', $time).'-'.$this->getConfigData('api_merchant', $order->getStoreId());

				if (empty($this->_cache[$ckey])) {
					$this->_cache[$ckey] = $this->callApi('report', 'report', [
						date('Y-m-d', $time - 3600), // -1h
						date('Y-m-d', min($time + 86400, strtotime('23:59:59'))), // +24h ou aujourd'hui 23h59
					], $order->getStoreId());
				}

				$lines = mb_substr($this->_cache[$ckey]['report'], mb_stripos($this->_cache[$ckey]['report'], 'LP;ID'));
				$heads = [];
				$data  = [];

				$resource = fopen('php://memory', 'rb+');
				fwrite($resource, $lines);
				rewind($resource);

				while (!empty($line = fgetcsv($resource, 50000, ';'))) {
					$line = array_map('trim', $line);
					if (empty($heads)) {
						$heads = $line;
						$key   = array_search('Opis transakcji', $line);
					}
					else if (count($line) == count($heads)) {
						foreach ($heads as $idx => $head)
							$data[preg_replace('#[^\d\-]#', '', $line[$key])][$head] = trim($line[$idx], '"');
					}
				}

				if (array_key_exists($order->getData('increment_id'), $data)) {
					$_SERVER['debug_tpay_response'] = $data[$order->getData('increment_id')];
					// Transaction ID (ID transakcji), Amount payed (Zapłacono)
					return $data[$order->getData('increment_id')]['ID transakcji'].','.(($data[$order->getData('increment_id')]['Zapłacono'] > 0) ? 'paid' : 'chargeback');
				}
			}
		}

		if (empty($captureId))
			return false;

		// https://docs.tpay.com/#!/Transaction_API/post_api_gw_api_key_transaction_get
		$response = $this->callApi('payment', 'get', [$captureId], $order->getStoreId());
		$response['id'] = $captureId;
		$response['amount'] = (float) $response['amount'];
		$response['amount_paid'] = (float) $response['amount_paid'];

		if ($search) // paid, pending, error, chargeback
			return in_array($response['status'], ['pending', 'error']) ? false : $captureId.','.$response['status'];

		return $response;
	}


	// on s'assure que les données viennent bien de Tpay (depuis processIpn sinon on redemande les infos via getTransaction)
	// les données POST ne servent à rien sauf en cas d'ipn (depuis processIpn)
	// retourne true uniquement si la commande est bien payée
	public function validatePayment(array $post, bool $ipn = false, bool $blik = false) {

		$check = false;
		if (!$ipn && ($this->_code == 'paymentmax_tpay')) { // the very old and shameful way
			$check = true;
			if (empty(Mage::app()->getRequest()->getParam('error')))
				return true;
		}

		$order    = $this->getInfoInstance()->getOrder();
		$isHolded = $order->getData('status') == 'holded';
		$payment  = $order->getPayment();

		// sentry
		$_SERVER['debug_tpay_order'] = $order->getData('increment_id');
		$_SERVER['debug_tpay_post']  = $post;
		$_SERVER['debug_tpay_ipn']   = $ipn ? 'yes' : 'no';
		$_SERVER['debug_tpay_blik']  = $blik ? 'yes' : 'no';

		if ($ipn) {

			// l'ipn arrive tellement vite
			sleep(2);
			$order->load($order->getId());

			$response = [
				'id'          => $post['tr_id'],
				'status'      => $post['tr_status'],
				'error_code'  => $post['tr_error'],
				'test_mode'   => !empty($post['test_mode']),
				'amount'      => (float) $post['tr_amount'],
				'amount_paid' => (float) $post['tr_paid'],
			];

			if (($response['status'] == 'FALSE') && ($response['error_code'] == 'overpay'))
				$response['status'] = 'paid';
			else if ($response['status'] == 'TRUE')
				$response['status'] = 'correct';
			else if ($response['status'] == 'PAID')
				$response['status'] = 'paid';
			else if ($response['status'] == 'FALSE')
				$response['status'] = 'incorrect';
			else if ($response['status'] == 'CHARGEBACK')
				$response['status'] = 'chargeback';
		}
		else if (Mage::app()->getRequest()->getParam('error') == 1) { // cancel button on tpay
			$response = ['id' => 0, 'amount' => 0, 'amount_paid' => 0, 'status' => 'error', 'err' => 'canceled by user'];
		}
		else {
			$response = $this->askTransaction($check);
		}

		// sentry
		$_SERVER['debug_tpay_response'] = $response;

		// paiement en attente
		// ne fait rien de plus
		if (empty($response['status']))
			return 'waiting';

		$status = $response['status'];
		if ($blik && ($status == 'pending'))
			return 'waiting';

		$captureId = $response['id'];

		// paiement remboursé
		// essaye de rembourser la commande
		if ($status == 'chargeback') {

			$this->refundOrder($order,
				$response['amount'],
				'PLN',
				$captureId,
				$captureId.'-ref-'.time(),
				[
					'amount_value'    => $response['amount'],
					'amount_currency' => 'PLN',
					'is_test'         => empty($response['test_mode']) ? 'no' : 'yes',
				]);

			return false;
		}

		// paiement validé
		// essaye de facturer la commande
		if (($response['amount_paid'] > 0) && ($response['amount_paid'] >= $response['amount']) && in_array($status, ['correct', 'paid'])) {

			$amount = $response['amount_paid'].' PLN';
			if ($order->getInvoiceCollection()->getSize() > 0) {
				$order->addStatusHistoryComment($this->getCommonMessage($ipn, $captureId).' Payment accepted ('.$amount.'). Order is already invoiced.')->save();
				return true;
			}

			if ($isHolded)
				$order->unhold();
			if ($this->uncancelOrder($order))
				$order->save();

			if ($order->canInvoice()) {

				$order->addStatusHistoryComment($this->getCommonMessage($ipn, $captureId).' Payment accepted ('.$amount.').')->save();

				$payment->setData('transaction_id', $captureId);
				$payment->setData('is_transaction_closed', false);
				$payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, [
					'amount_value'    => $response['amount_paid'],
					'amount_currency' => 'PLN',
					'is_test'         => empty($response['test_mode']) ? 'no' : 'yes',
				]);
				$payment->registerCaptureNotification($response['amount_paid'], true);
				$payment->setData('base_amount_paid_online', $response['amount_paid']);
				$payment->setData('base_amount_paid', $payment->getData('base_amount_ordered'));
				$payment->setData('amount_paid', $payment->getData('amount_ordered'));

				Mage::getModel('core/resource_transaction')
					->addObject($payment)
					->addObject($order)
					->save();

				$order->sendNewOrderEmail(true, '')->save();
				if (is_object($payment->getCreatedInvoice()))
					$payment->getCreatedInvoice()->sendEmail(true, '')->save();

				if ($response['error_code'] == 'overpay') {
					if ($order->canHold())
						$order->hold()->save();
					$order->addStatusHistoryComment($this->getCommonMessage($ipn, $captureId).' Overpaid! tr_amount='.$response['amount'].' tr_paid='.$response['amount_paid'])->save();
				}
			}
			else {
				$order->addStatusHistoryComment($this->getCommonMessage($ipn, $captureId).' Payment accepted ('.$amount.'). Order is not invoicable.')->save();
			}

			if ($isHolded && $order->canHold())
				$order->hold()->save();

			return true;
		}

 		// paiement refusé ou annulé
		// essaye d'annuler la commande
		if (in_array($status, ['pending', 'error'])) {

			if ($order->isCanceled()) {
				$order->addStatusHistoryComment($this->getCommonMessage($ipn, $captureId).' Payment canceled by user. Order is already canceled.')->save();
				return false;
			}

			if ($isHolded)
				$order->unhold();

			$codes  = \tpayLibs\src\Dictionaries\ErrorCodes\TransactionApiErrors::ERROR_CODES;
			$reason = empty($response['err']) ? (empty($response['error_code']) ? false : 'error_code:'.$response['error_code']) : $response['err'];
			$reason = (!empty($reason) && array_key_exists($reason, $codes)) ? $reason.' '.$codes[$reason] : $reason;
			$amount = ($reason ? $reason.', ' : '').$response['amount'].' PLN';

			if ($order->canCancel()) {

				$payment->setData('cc_status', $reason);
				$payment->setData('transaction_id', $captureId);
				$payment->setData('is_transaction_closed', true);
				$payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, [
					'refusal_reason'  => $reason,
					'amount_value'    => $response['amount'],
					'amount_currency' => 'PLN',
					'is_test'         => empty($response['test_mode']) ? 'no' : 'yes',
				]);

				Mage::dispatchEvent('sales_order_payment_cancel', ['payment' => $payment]);
				$payment->addTransaction('void');
				$order->cancel($this->getCommonMessage($ipn, $captureId).' '.(empty($reason) ? 'Payment canceled by customer' : 'Payment refused').' ('.$amount.').');

				Mage::getModel('core/resource_transaction')
					->addObject($payment)
					->addObject($order)
					->save();
			}
			else {
				$order->addStatusHistoryComment($this->getCommonMessage($ipn, $captureId).' '.(empty($reason) ? 'Payment canceled by customer' : 'Payment refused').' ('.$amount.'). Order is not cancellable.')->save();
				if ($order->canHold())
					$order->hold()->save();
			}

			return $reason;
		}

		Mage::throwException(new Exception('Tpay'.($ipn ? ' (ipn)' : '').' validatePayment error: '.
			print_r($post, true).' '.print_r($response, true)));
	}


	// on s'assure que les données viennent bien de Tpay (on redemande les infos via callApi)
	// les données POST sont traitées directement par la lib tpay (checkPayment) afin de trouver la bonne commande
	// retourne le résultat de validatePayment ou false
	public function processIpn(array $post) {

		$post = $this->callApi('ipn', 'checkPayment', []);

		if (isset($post['tr_crc'], $post['tr_desc'])) {
			$order   = Mage::getModel('sales/order')->load($post['tr_crc']);
			$payment = $order->getPayment();
			if (($order->getData('increment_id') == $post['tr_desc']) && in_array($payment->getData('method'), $this->_codes))
				return $payment->getMethodInstance()->validatePayment($post, true);
		}

		if (isset($post['tr_id'])) {

			$transactions = Mage::getResourceModel('sales/order_payment_transaction_collection')
				->addFieldToFilter('txn_id', $post['tr_id']);

			foreach ($transactions as $transaction) {
				$order   = Mage::getModel('sales/order')->load($transaction->getData('order_id'));
				$payment = $order->getPayment();
				if (in_array($payment->getData('method'), $this->_codes))
					return $payment->getMethodInstance()->validatePayment($post, true);
			}
		}

		return false;
	}
}