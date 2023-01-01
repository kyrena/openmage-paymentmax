<?php
/**
 * Created V/22/10/2021
 * Updated V/09/12/2022
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

class Kyrena_Paymentmax_Model_Payment_Yookassa extends Kyrena_Paymentmax_Model_Payment {

	protected $_code          = 'paymentmax_yookassa';
	protected $_formBlockType = 'paymentmax/payment_yookassa';
	protected $_codes  = ['paymentmax_yookassa', 'paymentmax_yookassaqiwi'];
	protected $_cache  = []; // transactions list


	// kyrena
	protected function callApi(string $method, array $params, int $storeId = 0) {

		$client = new \YooKassa\Client();
		$client->setAuth(
			$this->getConfigData('api_username', $storeId),
			Mage::helper('core')->decrypt($this->getConfigData('api_password', $storeId))
		);

		// sentry
		$_SERVER['debug_yookassa_method']  = $method;
		$_SERVER['debug_yookassa_request'] = $params;

		$response = $client->{$method}(...$params);

		// sentry
		$_SERVER['debug_yookassa_response'] = $response;

		//echo '<pre>',print_r($_POST, true),print_r($data, true),print_r($response, true);exit;
		return $response;
	}

	protected function askRefund(object $order, string $captureId, float $amount, bool $partial) {

		$refund = $order->getPayment()->getCreditmemo();

		$ip = empty(getenv('HTTP_X_FORWARDED_FOR')) ? false : explode(',', getenv('HTTP_X_FORWARDED_FOR'));
		$ip = empty($ip) ? getenv('REMOTE_ADDR') : reset($ip);

		// https://yookassa.ru/en/developers/api?lang=php#create_refund
		// attention aux setAdjustmentPositive et setAdjustmentNegative, avec la conversion de devise
		$items = [];
		foreach ($refund->getAllItems() as $item) {
			$items[] = [
				'description' => mb_substr($item->getData('name'), 0, 128),
				'quantity'    => $item->getData('qty'),
				'amount'      => [
					'value'    => ($item->getData('row_total_incl_tax') - $item->getData('discount_amount')) / $item->getData('qty'), // unit price
					'currency' => 'RUB', //$order->getOrderCurrencyCode()
				],
				// https://yookassa.ru/en/developers/54fz/parameters-values#vat-codes (20%, 10%, 0%)
				'vat_code' => ($item->getData('tax_percent') == 20) ? 4 : (($item->getData('tax_percent') == 10) ? 3 : 2),
			];
		}
		if ($refund->getData('fooman_surcharge_amount') > 0) {
			$items[] = [
				'description' => $order->getData('fooman_surcharge_description'),
				'quantity'    => 1,
				'amount'      => [
					'value'    => $refund->getData('fooman_surcharge_amount') + (float) $refund->getData('fooman_surcharge_tax_amount'),
					'currency' => 'RUB', //$order->getOrderCurrencyCode()
				],
				// https://yookassa.ru/en/developers/54fz/parameters-values#vat-codes (20% ou 0%)
				'vat_code' => ($refund->getData('fooman_surcharge_tax_amount') > 0) ? 4 : 2,
			];
		}
		if ($refund->getData('shipping_amount') > 0) {
			$items[] = [
				'description' => $order->getData('shipping_description'),
				'quantity'    => 1,
				'amount'      => [
					'value'    => $refund->getData('shipping_incl_tax'),
					'currency' => 'RUB', //$order->getOrderCurrencyCode()
				],
				// https://yookassa.ru/en/developers/54fz/parameters-values#vat-codes (20% ou 0%)
				'vat_code' => ($refund->getData('shipping_tax_amount') > 0) ? 4 : 2,
			];
		}
		if ($refund->getData('adjustment_negative') > 0) {
			$items[] = [
				'description' => Mage::helper('sales')->__('Adjustment Refund'),
				'quantity'    => 1,
				'amount'      => [
					'value'    => $refund->getData('adjustment_negative'),
					'currency' => 'RUB', //$order->getOrderCurrencyCode()
				],
				// https://yookassa.ru/en/developers/54fz/parameters-values#vat-codes (0%)
				'vat_code' => 2,
			];
		}
		if ($refund->getData('adjustment_positive') > 0) {
			$items[] = [
				'description' => Mage::helper('sales')->__('Adjustment Fee'),
				'quantity'    => 1,
				'amount'      => [
					'value'    => $refund->getData('adjustment_positive'),
					'currency' => 'RUB', //$order->getOrderCurrencyCode()
				],
				// https://yookassa.ru/en/developers/54fz/parameters-values#vat-codes (0%)
				'vat_code' => 2,
			];
		}

		$billing  = $order->getBillingAddress();
		$response = $this->callApi('createRefund', [[
			'payment_id'  => $captureId,
			'amount'      => [
				'value'    => $amount,
				'currency' => 'RUB', //$order->getOrderCurrencyCode()
			],
			'receipt'  => [
				'email'     => $order->getCustomerEmail(),
				'phone'     => $billing->getTelephone(),
				'customer' => [
					'full_name' => $order->getCustomerName(),
					'email'     => $order->getCustomerEmail(),
					'phone'     => $billing->getTelephone(),
				],
				'items' => $items,
			],
			'description' => Mage::helper('paymentmax')->__('Refund completed by %s (%s).', Mage::helper('paymentmax')->getUsername(), $ip),
		], time()], $order->getStoreId());

		return [
			'status'      => ($response->getStatus() == 'succeeded') ? 'success' : $response->getStatus(),
			'refundId'    => $response->getId(),
			'amount'      => $response->getAmount()->getValue(),
			'currency'    => $response->getAmount()->getCurrency(),
			'raw_details' => [
				'amount_value'    => $response->getAmount()->getValue(),
				'amount_currency' => $response->getAmount()->getCurrency(),
				'receipt'         => $response->getReceiptRegistration(),
			],
		];
	}

	protected function askRedirect(object $order) {

		$storeId = $order->getStoreId();

		$ip = empty(getenv('HTTP_X_FORWARDED_FOR')) ? false : explode(',', getenv('HTTP_X_FORWARDED_FOR'));
		$ip = empty($ip) ? getenv('REMOTE_ADDR') : reset($ip);

		// https://yookassa.ru/en/developers/api?lang=php#create_payment
		$items = [];
		foreach ($order->getAllVisibleItems() as $item) {
			$items[] = [
				'description' => mb_substr($item->getData('name'), 0, 128),
				'quantity'    => $item->getData('qty_ordered'),
				'amount'      => [
					'value'    => ($item->getData('row_total_incl_tax') - $item->getData('discount_amount')) / $item->getData('qty_ordered'), // unit price
					'currency' => 'RUB', //$order->getOrderCurrencyCode()
				],
				// https://yookassa.ru/en/developers/54fz/parameters-values#vat-codes (20%, 10%, 0%)
				'vat_code' => ($item->getData('tax_percent') == 20) ? 4 : (($item->getData('tax_percent') == 10) ? 3 : 2),
			];
		}
		if ($order->getData('fooman_surcharge_amount') > 0) {
			$items[] = [
				'description' => $order->getData('fooman_surcharge_description'),
				'quantity'    => 1,
				'amount'      => [
					'value'    => $order->getData('fooman_surcharge_amount') + $order->getData('fooman_surcharge_tax_amount'),
					'currency' => 'RUB', //$order->getOrderCurrencyCode()
				],
				// https://yookassa.ru/en/developers/54fz/parameters-values#vat-codes (20% ou 0%)
				'vat_code' => ($order->getData('fooman_surcharge_tax_amount') > 0) ? 4 : 2,
			];
		}
		if ($order->getData('shipping_amount') > 0) {
			$items[] = [
				'description' => $order->getData('shipping_description'),
				'quantity'    => 1,
				'amount'      => [
					'value'    => $order->getData('shipping_incl_tax') - $order->getData('shipping_discount_amount'),
					'currency' => 'RUB', //$order->getOrderCurrencyCode()
				],
				// https://yookassa.ru/en/developers/54fz/parameters-values#vat-codes (20% ou 0%)
				'vat_code' => ($order->getData('shipping_tax_amount') > 0) ? 4 : 2,
			];
		}

		$billing = $order->getBillingAddress();
		$data = [
			'amount' => [
				'value'    => $order->getTotalDue(),
				'currency' => 'RUB', //$order->getOrderCurrencyCode()
			],
			'description' => $order->getData('increment_id'),
			'receipt' => [
				'items'    => $items,
				'email'    => $order->getData('customer_email'),
				'phone'    => $billing->getData('telephone'),
				'customer' => [
					'full_name' => $order->getData('customer_firstname').' '.$order->getData('customer_lastname'),
					'email'     => $order->getData('customer_email'),
					'phone'     => $billing->getData('telephone'),
				],
			],
			'confirmation' => [
				'enforce'    => true,
				'type'       => 'redirect',
				'return_url' => $this->getOrderReturnUrl(),
			],
			'capture'   => true,
			'client_ip' => $ip,
			'metadata'  => [
				'order_id'     => $order->getId(),
				'increment_id' => $order->getData('increment_id'),
			],
		];

		if ($this->_code == 'paymentmax_yookassaqiwi')
			$data['payment_method_data'] = ['type' => 'qiwi'];

		$response = $this->callApi('createPayment', [$data, time()], $storeId);
		$url      = $response->getConfirmation()->getConfirmationUrl();

		$order->getPayment()->setData('last_trans_id', $response->getId())->save();
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

		// https://yookassa.ru/en/developers/api?lang=php#get_payments_list
		if ($search && empty($captureId)) {

			$page = null;
			$pnum = 0;
			do {
				$time = strtotime($order->getData('created_at'));
				$dkey = date('Ymd', $time).'-'.$this->getConfigData('api_username', $order->getStoreId()).'-'.$pnum;

				if (empty($this->_cache[$dkey])) {
					$this->_cache[$dkey] = $this->callApi('getPayments', [[
						'created_at_gte' => date('c', $time - 3600),  // -1h
						'created_at_lt'  => date('c', $time + 86400), // +24h
						'limit'          => 100,
						'cursor'         => $page,
					]], $order->getStoreId());
				}

				foreach ($this->_cache[$dkey]->getItems() as $payment) {

					$data = method_exists($payment, 'getMetadata') ? $payment->getMetadata() : null;
					$data = is_object($data) ? $data->toArray() : [];

					if (!empty($data['order_id']) && !empty($data['increment_id']) && ($data['increment_id'] == $order->getData('increment_id')) && ($data['order_id'] == $order->getId())) {
						$refunded = is_object($payment->getRefundedAmount()) && ($payment->getRefundedAmount()->getValue() > 0);
						return (!$payment->getPaid() && !$refunded) ? false : $payment->getId().','.($refunded ? 'chargeback' : 'paid');
					}
				}

				$pnum++;
			}
			while ($page = $this->_cache[$dkey]->getNextCursor());
		}

		if (empty($captureId))
			return false;

		// https://yookassa.ru/en/developers/api?lang=php#get_payment
		$response = $this->callApi('getPaymentInfo', [$captureId], $order->getStoreId());

		if ($search) { // est non payée si non payée et sans remboursement
			$refunded = is_object($response->getRefundedAmount()) && ($response->getRefundedAmount()->getValue() > 0);
			return (!$response->getPaid() && !$refunded) ? false : $response->getId().','.($refunded ? 'chargeback': 'paid');
		}

		return $response;
	}


	// on s'assure que les données viennent bien de YooKassa (on redemande toujours les infos via getTransaction)
	// les données POST ne servent à rien sauf à indiquer le numéro de transaction d'un remboursement (depuis processIpn)
	// retourne true uniquement si la commande est bien payée
	public function validatePayment(array $post, bool $ipn = false) {

		$order     = $this->getInfoInstance()->getOrder();
		$isHolded  = $order->getStatus() == 'holded';
		$payment   = $order->getPayment();
		$response  = $this->askTransaction();
		$captureId = $response->getId();

		// sentry
		$_SERVER['debug_yookassa_order'] = $order->getData('increment_id');
		$_SERVER['debug_yookassa_post']  = $post;
		$_SERVER['debug_yookassa_ipn']   = $ipn ? 'yes' : 'no';

		if ($response->getStatus() == 'waiting_for_capture')
			return false;

		// paiement remboursé
		// essaye de rembourser la commande
		if (is_object($response->getRefundedAmount()) && ($response->getRefundedAmount()->getValue() > 0)) {

			// l'ipn arrive en même temps que la demande de remboursement
			sleep(2);
			$order->load($order->getId());

			$this->refundOrder($order,
				$response->getRefundedAmount()->getValue(),
				$response->getAmount()->getCurrency(),
				$captureId,
				$post['object']['id'],
				[
					'amount_value'    => $response->getRefundedAmount()->getValue(),
					'amount_currency' => $response->getAmount()->getCurrency(),
					'is_paid'         => $response->getPaid() ? 'yes' : 'no',
					'is_refundable'   => $response->getRefundable() ? 'yes' : 'no',
					'is_test'         => $response->getTest() ? 'yes' : 'no',
					'receipt'         => $response->getReceiptRegistration(),
				]);

			return false;
		}

		// paiement validé
		// essaye de facturer la commande
		if ($response->getPaid() && ($response->getStatus() == 'succeeded')) {

			$amount = $response->getAmount()->getValue().' '.$response->getAmount()->getCurrency();
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

				$responsePayment = $response->getPaymentMethod();
				$responsePayment = is_object($responsePayment) ? $responsePayment : new stdClass();

				$payment->setData('transaction_id', $captureId);
				$payment->setData('cc_exp_month',   method_exists($responsePayment, 'getExpiryMonth') ? $responsePayment->getExpiryMonth() : null);
				$payment->setData('cc_exp_year',    method_exists($responsePayment, 'getExpiryYear')  ? $responsePayment->getExpiryYear() : null);
				$payment->setData('cc_type',        method_exists($responsePayment, 'getCardType')    ? $responsePayment->getCardType() : null);
				$payment->setData('cc_last4',       method_exists($responsePayment, 'getLast4')       ? $responsePayment->getLast4() : null);
				$payment->setData('is_transaction_closed', $response->getRefundable() ? false : true);
				$payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, [
					'auth_code'       => is_object($response->getAuthorizationDetails()) ? $response->getAuthorizationDetails()->getAuthCode() : null,
					'method_type'     => method_exists($responsePayment, 'getType')          ? $responsePayment->getType() : null,
					'method_title'    => method_exists($responsePayment, 'getTitle')         ? $responsePayment->getTitle() : null,
					'card_country'    => method_exists($responsePayment, 'getIssuerCountry') ? $responsePayment->getIssuerCountry() : null,
					'card_number'     => method_exists($responsePayment, 'getFirst6')        ? $responsePayment->getFirst6().'XXXXXX'.$responsePayment->getLast4() : null,
					'card_expire'     => method_exists($responsePayment, 'getExpiryMonth')   ? $responsePayment->getExpiryMonth().'/'.$responsePayment->getExpiryYear() : null,
					'card_type'       => method_exists($responsePayment, 'getCardType')      ? $responsePayment->getCardType() : null,
					'amount_value'    => $response->getAmount()->getValue(),
					'amount_currency' => $response->getAmount()->getCurrency(),
					'is_paid'         => $response->getPaid() ? 'yes' : 'no',
					'is_refundable'   => $response->getRefundable() ? 'yes' : 'no',
					'is_test'         => $response->getTest() ? 'yes' : 'no',
					'receipt'         => $response->getReceiptRegistration(),
				]);
				$payment->registerCaptureNotification($response->getAmount()->getValue(), true);
				$payment->setData('base_amount_paid_online', $response->getAmount()->getValue());
				$payment->setData('base_amount_paid', $payment->getData('base_amount_ordered'));
				$payment->setData('amount_paid', $payment->getData('amount_ordered'));

				Mage::getModel('core/resource_transaction')
					->addObject($payment)
					->addObject($order)
					->save();

				$order->sendNewOrderEmail(true, '')->save();
				if (is_object($payment->getCreatedInvoice()))
					$payment->getCreatedInvoice()->sendEmail(true, '')->save();
			}
			else {
				$order->addStatusHistoryComment($this->getCommonMessage($ipn, $captureId).' Payment accepted ('.$amount.'). Order is not invoicable.')->save();
			}

			if ($isHolded && $order->canHold())
				$order->hold()->save();

			return true;
		}

 		// paiement refusé ou annulé
		// essayer d'annuler la commande
		if (!$response->getPaid() || ($response->getStatus() != 'succeeded')) {

			if ($order->isCanceled()) {
				$order->addStatusHistoryComment($this->getCommonMessage($ipn, $captureId).' Payment canceled by user. Order is already canceled.')->save();
				return false;
			}

			if ($isHolded)
				$order->unhold();

			$reason = $response->getCancellationDetails() ? $response->getCancellationDetails()->getReason() : false;
			$amount = ($reason ? $reason.', ' : '').$response->getAmount()->getValue().' '.$response->getAmount()->getCurrency();

			if ($order->canCancel()) {

				$responsePayment = $response->getPaymentMethod();
				$responsePayment = is_object($responsePayment) ? $responsePayment : new stdClass();

				$payment->setData('cc_status',      $reason);
				$payment->setData('transaction_id', $captureId);
				$payment->setData('cc_exp_month',   method_exists($responsePayment, 'getExpiryMonth') ? $responsePayment->getExpiryMonth() : null);
				$payment->setData('cc_exp_year',    method_exists($responsePayment, 'getExpiryYear')  ? $responsePayment->getExpiryYear() : null);
				$payment->setData('cc_type',        method_exists($responsePayment, 'getCardType')    ? $responsePayment->getCardType() : null);
				$payment->setData('cc_last4',       method_exists($responsePayment, 'getLast4')       ? $responsePayment->getLast4() : null);
				$payment->setData('is_transaction_closed', true);
				$payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, [
					'refusal_reason'  => $reason,
					'method_type'     => method_exists($responsePayment, 'getType')          ? $responsePayment->getType() : null,
					'method_title'    => method_exists($responsePayment, 'getTitle')         ? $responsePayment->getTitle() : null,
					'card_country'    => method_exists($responsePayment, 'getIssuerCountry') ? $responsePayment->getIssuerCountry() : null,
					'card_number'     => method_exists($responsePayment, 'getFirst6')        ? $responsePayment->getFirst6().'XXXXXX'.$responsePayment->getLast4() : null,
					'card_expire'     => method_exists($responsePayment, 'getExpiryMonth')   ? $responsePayment->getExpiryMonth().'/'.$responsePayment->getExpiryYear() : null,
					'card_type'       => method_exists($responsePayment, 'getCardType')      ? $responsePayment->getCardType() : null,
					'amount_value'    => $response->getAmount()->getValue(),
					'amount_currency' => $response->getAmount()->getCurrency(),
					'is_paid'         => $response->getPaid() ? 'yes' : 'no',
					'is_refundable'   => $response->getRefundable() ? 'yes' : 'no',
					'is_test'         => $response->getTest() ? 'yes' : 'no',
					'receipt'         => $response->getReceiptRegistration(),
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

		Mage::throwException(new Exception('YooKassa'.($ipn ? ' (ipn)' : '').' validatePayment error: '.
			print_r($post, true).' '.print_r($response, true)));
	}


	// on fait comme si les données viennent bien de YooKassa
	// utilise order_id,increment_id,payment_id des données POST pour trouver la commande
	// retourne le résultat de validatePayment ou false
	public function processIpn(array $post) {

		// https://yookassa.ru/en/developers/using-api/webhooks
		// payment.waiting_for_capture payment.succeeded payment.canceled refund.succeeded
		$factory = new \YooKassa\Model\Notification\NotificationFactory();
		$notification = $factory->factory($post)->getObject();

		$data = method_exists($notification, 'getMetadata') ? $notification->getMetadata() : null;
		$data = is_object($data) ? $data->toArray() : [];

		if (isset($data['order_id'], $data['increment_id'])) {
			$order   = Mage::getModel('sales/order')->load($data['order_id']);
			$payment = $order->getPayment();
			if (($order->getData('increment_id') == $data['increment_id']) && in_array($payment->getData('method'), $this->_codes))
				return $payment->getMethodInstance()->validatePayment($post, true);
		}

		if (isset($post['object']['payment_id'])) {

			$transactions = Mage::getResourceModel('sales/order_payment_transaction_collection')
				->addFieldToFilter('txn_id', $post['object']['payment_id']);

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