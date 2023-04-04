<?php
/**
 * Created V/22/10/2021
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

class Kyrena_Paymentmax_Model_Payment_Paypal extends Kyrena_Paymentmax_Model_Payment {

	protected $_code          = 'paymentmax_paypal';
	protected $_formBlockType = 'paymentmax/payment_paypal';
	protected $_codes         = ['paymentmax_paypal', 'paymentmax_paypalcheckout', 'paypal_billing_agreement', 'paypal_direct', 'paypal_express', 'paypal_express_bml', 'paypal_standard', 'paypal_wps_express', 'paypaluk_express', 'paypaluk_direct', 'verisign', 'hosted_pro', 'payflow_link', 'payflow_advanced'];


	// openmage
	public function getCode() {
		return in_array($this->_code, $this->_allcnf) ? $this->_code : 'paymentmax_paypal';
	}


	// kyrena
	protected function addAmountsToRequest(array $data, bool $isTest = false) {

		$order = $this->getInfoInstance()->getOrder();
		$currencyCode = $order->getData('order_currency_code');

		// https://developer.paypal.com/docs/nvp-soap-api/set-express-checkout-nvp/
		$data['PAYMENTREQUEST_0_AMT']              = $order->getTotalDue();
		$data['PAYMENTREQUEST_0_CURRENCYCODE']     = $currencyCode;
		$data['PAYMENTREQUEST_0_ITEMAMT']          = $order->getData('subtotal_incl_tax') - abs($order->getData('discount_amount')) - $order->getData('tax_amount');
		$data['PAYMENTREQUEST_0_TAXAMT']           = $order->getData('tax_amount') + $order->getData('shipping_tax_amount');
		$data['PAYMENTREQUEST_0_INVNUM']           = $order->getData('increment_id').($isTest ? ':'.strtotime($order->getData('created_at')) : '');
		$data['PAYMENTREQUEST_0_DESC']             = $order->getData('increment_id');
		$data['PAYMENTREQUEST_0_NOTIFYURL']        = $this->getOrderIpnUrl(['id' => $order->getData('increment_id')]);
		$data['PAYMENTREQUEST_0_PAYMENTACTION']    = 'Sale';
		$data['PAYMENTREQUEST_0_PAYMENTREQUESTID'] = $order->getData('increment_id');

		$data['PAYMENTREQUEST_0_HANDLINGAMT'] = 0;
		if ($order->getData('fooman_surcharge_amount') > 0)
			$data['PAYMENTREQUEST_0_HANDLINGAMT'] = $order->getData('fooman_surcharge_amount') + $order->getData('fooman_surcharge_tax_amount');
		$data['PAYMENTREQUEST_0_SHIPPINGAMT'] = 0;
		if ($order->getData('shipping_amount') > 0)
			$data['PAYMENTREQUEST_0_SHIPPINGAMT'] = (float) $order->getData('shipping_amount');

		// 10413 Transaction refused because of an invalid argument
		//  The totals of the cart item amounts do not match order amounts
		$total = $data['PAYMENTREQUEST_0_ITEMAMT'] + $data['PAYMENTREQUEST_0_TAXAMT'];
		if (!empty($data['PAYMENTREQUEST_0_HANDLINGAMT']))
			$total += $data['PAYMENTREQUEST_0_HANDLINGAMT'];
		if (!empty($data['PAYMENTREQUEST_0_SHIPPINGAMT']))
			$total += $data['PAYMENTREQUEST_0_SHIPPINGAMT'];

		// Retourne 0 si les deux opérandes sont égaux, 1 si l'opérande num1 est plus grand que l'opérande num2, -1 sinon.
		$diff = bccomp($total, $data['PAYMENTREQUEST_0_AMT'], 2);
		if ($diff == 1) {
			if ($data['PAYMENTREQUEST_0_TAXAMT'] > 0)
				$data['PAYMENTREQUEST_0_TAXAMT']  -= abs($total - $data['PAYMENTREQUEST_0_AMT']);
			else
				$data['PAYMENTREQUEST_0_ITEMAMT'] -= abs($total - $data['PAYMENTREQUEST_0_AMT']);
		}
		else if ($diff == -1) {
			if ($data['PAYMENTREQUEST_0_TAXAMT'] > 0)
				$data['PAYMENTREQUEST_0_TAXAMT']  += abs($total - $data['PAYMENTREQUEST_0_AMT']);
			else
				$data['PAYMENTREQUEST_0_ITEMAMT'] += abs($total - $data['PAYMENTREQUEST_0_AMT']);
		}

		$address = $order->getIsVirtual() ? $order->getBillingAddress() : $order->getShippingAddress();
		$data['PAYMENTREQUEST_0_SHIPTONAME']        = $address->getName();
		$data['PAYMENTREQUEST_0_SHIPTOSTREET']      = $address->getStreet(1);
		$data['PAYMENTREQUEST_0_SHIPTOSTREET2']     = $address->getStreet(2);
		$data['PAYMENTREQUEST_0_SHIPTOCITY']        = $address->getData('city');
		$data['PAYMENTREQUEST_0_SHIPTOSTATE']       = $address->getData('region');
		$data['PAYMENTREQUEST_0_SHIPTOZIP']         = $address->getData('postcode');
		$data['PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE'] = $address->getData('country_id');
		$data['PAYMENTREQUEST_0_SHIPTOPHONENUM']    = $address->getData('telephone');

		//echo '<pre>',print_r($data, true);
		return $data;
	}

	protected function callApi(array $data, int $storeId = 0, bool $isTest = false, bool $check = true) {

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);

		if (empty($data['url'])) {
			$data['PWD']       = Mage::helper('core')->decrypt($this->getConfigData('api_password', $storeId));
			$data['USER']      = Mage::helper('core')->decrypt($this->getConfigData('api_username', $storeId));
			$data['SIGNATURE'] = Mage::helper('core')->decrypt($this->getConfigData('api_signature', $storeId));
			$data['VERSION']   = 101;
			curl_setopt($ch, CURLOPT_URL, $isTest ? 'https://api-3t.sandbox.paypal.com/nvp' : 'https://api-3t.paypal.com/nvp');
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
		}
		else {
			curl_setopt($ch, CURLOPT_URL, $data['url']);
			curl_setopt($ch, CURLOPT_HTTPGET, true);
		}

		$result = curl_exec($ch);
		if (($result === false) || (curl_errno($ch) !== 0)) {
			$result   = trim('CURL_ERROR '.curl_errno($ch).' '.curl_error($ch));
			$response = $result;
		}
		else if (empty($data['url'])) {
			mb_parse_str($result, $response);
			if (empty($response['ACK']))
				$response['ACK'] = 'Err';
			if (empty($response['L_ERRORCODE0']))
				$response['L_ERRORCODE0'] = 0;
		}
		else {
			$response = $result;
		}
		curl_close($ch);

		// sentry
		unset($data['PWD'], $data['USER'], $data['SIGNATURE']);
		$_SERVER['debug_paypal_request'] = $data;
		$_SERVER['debug_paypal_response_raw'] = $result;
		$_SERVER['debug_paypal_response_decoded'] = $response;
		//echo '<pre>',print_r($_POST, true),print_r($data, true),print_r($response, true);exit;

		if ($check && (!is_array($response) || ($response['ACK'] != 'Success')))
			Mage::throwException(empty($response['L_ERRORCODE0']) ?
				sprintf('Error with PayPal: %s', print_r($response, true)) :
				sprintf('Error with PayPal: %s: %s %s', $response['L_ERRORCODE0'], str_replace(' See additional error messages for details.', '', $response['L_SHORTMESSAGE0']), $response['L_LONGMESSAGE0']));

		return (array) $response;
	}

	protected function askRefund(object $order, string $captureId, float $amount, bool $partial) {

		$storeId = $order->getStoreId();
		$isTest  = $this->getConfigFlag('api_sandbox', $storeId);

		$ip = empty(getenv('HTTP_X_FORWARDED_FOR')) ? false : explode(',', getenv('HTTP_X_FORWARDED_FOR'));
		$ip = empty($ip) ? getenv('REMOTE_ADDR') : reset($ip);

		// https://developer.paypal.com/docs/nvp-soap-api/refund-transaction-nvp/
		$response = $this->callApi([
			'METHOD'        => 'RefundTransaction',
			'TRANSACTIONID' => $captureId,
			'REFUNDTYPE'    => $partial ? 'Partial' : 'Full',
			'AMT'           => $amount,
			'CURRENCYCODE'  => $order->getData('order_currency_code'),
			'NOTE'          => Mage::helper('paymentmax')->__('Refund completed by %s (%s).', Mage::helper('paymentmax')->getUsername(), $ip),
		], $storeId, $isTest);

		return [
			'status'      => ($response['REFUNDSTATUS'] == 'none') ? 'error' : 'success',
			'refundId'    => $response['REFUNDTRANSACTIONID'],
			'amount'      => $response['GROSSREFUNDAMT'],
			'currency'    => $response['CURRENCYCODE'],
			'raw_details' => [
				'amount_value'    => $response['GROSSREFUNDAMT'],
				'amount_currency' => $response['CURRENCYCODE'],
				'refund_status'   => $response['REFUNDSTATUS'],
				'refund_pending'  => $response['PENDINGREASON'],
				'is_test'         => $isTest ? 'yes' : 'no',
			],
		];
	}

	protected function askRedirect(object $order) {

		$storeId = $order->getStoreId();
		$isTest  = $this->getConfigFlag('api_sandbox', $storeId);

		// https://developer.paypal.com/docs/nvp-soap-api/set-express-checkout-nvp/
		$response = $this->callApi($this->addAmountsToRequest([
			'METHOD'       => 'SetExpressCheckout',
			'RETURNURL'    => $this->getOrderReturnUrl(),
			'CANCELURL'    => $this->getOrderReturnUrl(),
			'NOSHIPPING'   => 1,
			'ADDROVERRIDE' => 0,
			'EMAIL'        => $order->getData('customer_email'),
			'SOLUTIONTYPE' => 'Sole',
			'TOTALTYPE'    => 'Total',
			'BRANDNAME'    => Mage::getStoreConfig('general/store_information/name', $storeId).($isTest ? ' // TEST' : ''),
		], $isTest), $storeId, $isTest);

		$url = $isTest ?
			'https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&useraction=commit&token='.$response['TOKEN'] :
			'https://www.paypal.com/webscr?cmd=_express-checkout&useraction=commit&token='.$response['TOKEN'];

		$order->getPayment()->setData('last_trans_id', $response['TOKEN'])->save();
		$order->addStatusHistoryComment('Customer redirected to: '.$url)->save();

		return $url;
	}


	// retourne les données de la transaction
	// demande via le numéro de transaction enregistré par redirectToPayment, sinon fait une recherche par date
	// si on fait une recherche retourne false si la commande n'est pas payée, sinon retourne l'id de la transaction et son statut
	public function askTransaction(bool $search = false) {

		$order     = $this->getInfoInstance()->getOrder();
		$storeId   = $order->getStoreId();
		$payment   = $order->getPayment();
		$captureId = $payment->lookupTransaction(null, Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE);
		$captureId = (empty($captureId) || empty($captureId->getTxnId())) ? $payment->getData('last_trans_id') : $captureId->getTxnId();

		// il peut y avoir le token (EC-XYZ), donc on l'ignore
		if (!empty($captureId) && str_contains($captureId, 'EC-'))
			$captureId = null;

		// https://developer.paypal.com/docs/nvp-soap-api/transaction-search-nvp/
		if ($search && empty($captureId)) {

			$time = strtotime($order->getData('created_at'));
			$isTest   = $this->getConfigFlag('api_sandbox', $storeId);
			$response = $this->callApi([
				'METHOD'    => 'TransactionSearch',
				'INVNUM'    => $isTest ? $order->getData('increment_id').':'.$time : $order->getData('increment_id'),
				'STARTDATE' => date('c', $time - 86400), // -24h
				'ENDDATE'   => date('c', $time + 86400), // +24h
			], $storeId, $isTest);

			return empty($response['L_TRANSACTIONID0']) ? false : $response['L_TRANSACTIONID0'].','.$response['L_STATUS0'];
		}

		if (empty($captureId))
			return false;

		// https://developer.paypal.com/api/nvp-soap/get-transaction-details-nvp/
		$response = $this->callApi([
			'METHOD'        => 'GetTransactionDetails',
			'TRANSACTIONID' => $captureId,
		], $storeId, $this->getConfigFlag('api_sandbox', $storeId));

		if ($search)
			return empty($response['TRANSACTIONID']) ? false : $response['TRANSACTIONID'].','.$response['PAYMENTSTATUS'];

		return $response;
	}


	// on s'assure que les données viennent bien de PayPal (depuis processIpn sinon ?)
	// utilise uniquement PayerID,token,txnid des données POST, utilise tout en cas d'ipn (depuis processIpn)
	// retourne true uniquement si la commande est bien payée
	public function validatePayment(array $post, bool $ipn = false) {

		// retourne true uniquement si la commande est bien payée
		$order     = $this->getInfoInstance()->getOrder();
		$storeId   = $order->getStoreId();
		$isHolded  = $order->getData('status') == 'holded';
		$isTest    = $this->getConfigFlag('api_sandbox', $storeId);
		$payment   = $order->getPayment();
		$success   = false;
		$refused   = false;
		$refund    = false;
		$response  = [];

		// sentry
		$_SERVER['debug_paypal_order'] = $order->getData('increment_id');
		$_SERVER['debug_paypal_post']  = $post;
		$_SERVER['debug_paypal_ipn']   = $ipn ? 'yes' : 'no';

		if (empty($post['txn_type']))
			$post['txn_type'] = 'n/a';
		if (empty($post['payment_status']))
			$post['payment_status'] = 'n/a';

		// PayPal Express ou PayPal IPN
		// https://.../paymentmax/paypal/return/?token=EC-XYZ&PayerID=XYZ : refused
		// https://.../paymentmax/paypal/return/?token=EC-XYZ&PayerID=XYZ : success
		// https://.../paymentmax/paypal/return/?token=EC-XYZ : button cancel
		// https://.../paymentmax/paypal/ipn/id/xyz/ : ipn
		if ($ipn) {

			$post['PayerID'] = $post['payer_id'];

			// https://developer.paypal.com/docs/api-basics/notifications/ipn/IPNIntro/
			$response['PAYMENTINFO_0_AMT']           = $post['mc_gross'] ?? null;
			$response['PAYMENTINFO_0_CURRENCYCODE']  = $post['mc_currency'] ?? null;
			$response['PAYMENTINFO_0_TRANSACTIONID'] = $post['txn_id'] ?? null;
			$response['PAYMENTINFO_0_PAYMENTTYPE']   = $post['payment_type'] ?? null;
			if (!empty($post['parent_txn_id']))
				$response['PAYMENTINFO_0_PARENTTRANSACTIONID'] = $post['parent_txn_id'];
			if (!empty($response['PAYMENTINFO_0_AMT']))
				$response['PAYMENTINFO_0_AMT'] = abs($response['PAYMENTINFO_0_AMT']);

			$isTest  = !empty($post['test_ipn']);
			$success = $post['payment_status'] == 'Completed';
			$refund  = $post['payment_status'] == 'Refunded';
			$refused = $post['payment_status'] == 'Denied';
		}

		if (!empty($post['PayerID'])) {

			// demande le paiement à PayPal
			// PayPal Express
			if (!$ipn && empty($post['txnid'])) {

				// https://developer.paypal.com/docs/nvp-soap-api/do-express-checkout-payment-nvp/
				$response = $this->callApi($this->addAmountsToRequest([
					'METHOD'  => 'DoExpressCheckoutPayment',
					'TOKEN'   => $payment->getData('last_trans_id'),
					'PAYERID' => $post['PayerID'],
				], $isTest), $storeId, $isTest, false);

				// Success ou SuccessWithWarning avec 11607 pour Duplicate Request (A successful txn has already been completed for this token)
				// Failure ou 13113 pour The Buyer cannot pay with PayPal for this Transaction
				if (!empty($response['ACK'])) {

					$success = ($response['ACK'] == 'Success') || (($response['ACK'] == 'SuccessWithWarning') && ($response['L_ERRORCODE0'] == 11607) && ($response['PAYMENTINFO_0_PAYMENTSTATUS'] == 'Completed'));
					$refused = ($response['ACK'] == 'Failure') || ($response['L_ERRORCODE0'] == 13113);

					if ($response['L_ERRORCODE0'] == 10486) {

						$url = $isTest ?
							'https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&useraction=commit&token='.$response['TOKEN'] :
							'https://www.paypal.com/webscr?cmd=_express-checkout&useraction=commit&token='.$response['TOKEN'];

						$order->addStatusHistoryComment('Customer redirected again to: '.$url)->save();
						header('Location: '.$url);
						exit(0); // stop redirection
					}
				}
			}
			// PayPal Checkout
			else if (!$ipn) {

				// https://developer.paypal.com/api/nvp-soap/get-transaction-details-nvp/
				$response = $this->callApi([
					'METHOD'        => 'GetTransactionDetails',
					'TRANSACTIONID' => $post['txnid'],
				], $storeId, $isTest);

				$success = ($response['ACK'] == 'Success') || (($response['ACK'] == 'SuccessWithWarning') && ($response['L_ERRORCODE0'] == 11607) && ($response['PAYMENTSTATUS'] == 'Completed'));
				$refused = ($response['ACK'] == 'Failure') || ($response['L_ERRORCODE0'] == 13113);

				$response['PAYMENTINFO_0_TRANSACTIONID'] = $post['txnid'];
				$response['PAYMENTINFO_0_AMT']           = $response['AMT'];
				$response['PAYMENTINFO_0_CURRENCYCODE']  = $response['CURRENCYCODE'];
				$response['PAYMENTINFO_0_PAYMENTTYPE']   = $response['PAYMENTTYPE'];
			}

			$captureId = $response['PAYMENTINFO_0_TRANSACTIONID'] ?? null;

			// paiement remboursé
			// essaye de rembourser la commande
			if ($refund) {

				$this->refundOrder($order,
					$response['PAYMENTINFO_0_AMT'],
					$response['PAYMENTINFO_0_CURRENCYCODE'],
					$response['PAYMENTINFO_0_PARENTTRANSACTIONID'],
					$response['PAYMENTINFO_0_TRANSACTIONID'],
					[
						'amount_value'    => $response['PAYMENTINFO_0_AMT'],
						'amount_currency' => $response['PAYMENTINFO_0_CURRENCYCODE'],
						'refund_status'   => $response['PAYMENTINFO_0_PAYMENTTYPE'],
						'is_test'         => $isTest ? 'yes' : 'no',
					]);

				return false;
			}

			// paiement validé
			// essaye de facturer la commande
			if ($success) {

				$amount = $response['PAYMENTINFO_0_AMT'].' '.$response['PAYMENTINFO_0_CURRENCYCODE'];
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
						'amount_value'    => $response['PAYMENTINFO_0_AMT'],
						'amount_currency' => $response['PAYMENTINFO_0_CURRENCYCODE'],
						'payment_status'  => $response['PAYMENTINFO_0_PAYMENTTYPE'],
						'is_test'         => $isTest ? 'yes' : 'no',
					]);
					$payment->registerCaptureNotification($response['PAYMENTINFO_0_AMT'], true);
					$payment->setData('base_amount_paid_online', $response['PAYMENTINFO_0_AMT']);
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

			// paiement problème
			// uniquement sur ipn
			if ($ipn) {

				if ($post['payment_status'] == 'Reversed') {

					$info = [];
					if (!empty($post['case_id']))
						$info[] = $post['case_id'];
					if (!empty($post['mc_gross']))
						$info[] = $post['mc_gross'].(empty($post['mc_currency']) ? '' : ' '.$post['mc_currency']);
					$info = empty($info) ? '' : ' '.implode(', ', $info);

					$order->addStatusHistoryComment($this->getCommonMessage($ipn, $captureId).' WARNING! Buyer complaint'.$info.'.')->save();
					if ($order->canHold()) {
						$order->hold()->save();
					}
					else if ($order->getData('status') != 'paypal_reversed') {
						$order->setData('hold_before_state', $order->getData('state'));
						$order->setData('hold_before_status', $order->getData('status'));
						$order->setData('status', 'paypal_reversed');
						$order->save();
					}

					return false;
				}

				if ($post['txn_type'] == 'new_case') {

					$info = [];
					if (!empty($post['case_id']))
						$info[] = $post['case_id'];
					if (!empty($post['mc_gross']))
						$info[] = $post['mc_gross'].(empty($post['mc_currency']) ? '' : ' '.$post['mc_currency']);
					$info = empty($info) ? '' : ' '.implode(', ', $info);

					$order->addStatusHistoryComment($this->getCommonMessage($ipn, $captureId).' WARNING! Buyer complaint'.$info.'.')->save();
					if ($order->canHold()) {
						$order->hold()->save();
					}
					else if ($order->getData('status') != 'paypal_reversed') {
						$order->setData('hold_before_state', $order->getData('state'));
						$order->setData('hold_before_status', $order->getData('status'));
						$order->setData('status', 'paypal_reversed');
						$order->save();
					}

					return false;
				}

				if ($post['payment_status'] == 'Canceled_Reversal') {

					$info = [];
					if (!empty($post['case_id']))
						$info[] = $post['case_id'];
					if (!empty($post['mc_gross']))
						$info[] = $post['mc_gross'].(empty($post['mc_currency']) ? '' : ' '.$post['mc_currency']);
					$info = empty($info) ? '' : ' '.implode(', ', $info);

					$order->addStatusHistoryComment($this->getCommonMessage($ipn, $captureId).' Buyer complaint resolved'.$info.'.')->save();
					if ($order->canUnhold() && (($order->getData('state') == 'holded') || ($order->getData('status') == 'holded'))) {
						$order->unhold()->save();
					}
					else if (!empty($status = $order->getData('hold_before_status'))) {
						$order->setData('state', $order->getData('hold_before_state'))->setData('hold_before_state', null);
						$order->setData('status', $status)->setData('hold_before_status', null);
						$order->save();
					}
					else {
						$order->setData('state', $order->canShip() ? 'processing' : 'complete');
						$order->setData('status', $order->getData('state'));
						$order->save();
					}

					return false;
				}
			}
		}

		// paiement en attente
		// ne fait rien de plus
		if ($post['payment_status'] == 'Pending')
			return false;

		// paiement annulé ou refusé
		// essayer d'annuler la commande
		if ($refused || (!empty($post['token']) && empty($post['PayerID']))) {

			$captureId = $response['PAYMENTINFO_0_TRANSACTIONID'] ?? null;

			// lorsque le client fait retour depuis la page success
			// il se retrouve sur PayPal qui affiche un bouton retourner sur le site marchand
			// ce bouton est un lien qui ne contient que le token
			if (!$ipn && ($order->getInvoiceCollection()->getSize() > 0)) {
				$order->addStatusHistoryComment($this->getCommonMessage($ipn, $captureId).' Order is already invoiced.')->save();
				return true;
			}

			// lorsque le client fait retour depuis la page success
			// il se retrouve sur PayPal qui affiche un bouton retourner sur le site marchand
			// si le paiement a été refusé, on ne le sais pas ici
			if ($order->isCanceled()) {
				$order->addStatusHistoryComment($this->getCommonMessage($ipn, $captureId).' '.($refused ? 'Payment refused. Order is already canceled.' : 'Payment canceled by user. Order is already canceled.'))->save();
				return $refused ? ($response['L_LONGMESSAGE0'] ?? $post['payment_status']) : false;
			}

			if ($isHolded)
				$order->unhold();

			if ($order->canCancel()) {

				Mage::dispatchEvent('sales_order_payment_cancel', ['payment' => $payment]);
				$payment->addTransaction('void');
				$order->cancel($this->getCommonMessage($ipn, $captureId).' '.($refused ? 'Payment refused.' : 'Payment canceled by customer.'));

				Mage::getModel('core/resource_transaction')
					->addObject($payment)
					->addObject($order)
					->save();
			}
			else {
				$order->addStatusHistoryComment($this->getCommonMessage($ipn, $captureId).' '.($refused ? 'Payment refused. Order is not cancellable.' : 'Payment canceled by customer. Order is not cancellable.'))->save();
				if ($order->canHold())
					$order->hold()->save();
			}

			return $refused ? ($response['L_LONGMESSAGE0'] ?? $post['payment_status']) : false;
		}

		Mage::throwException(new Exception('PayPal'.($ipn ? ' (ipn)' : '').($isTest ? ' (test)' : '').' validatePayment error: '.
			print_r($post, true).' '.print_r($response, true)));
	}


	// on s'assure que les données viennent bien de PayPal (on demande confirmation via callApi)
	// vérifie l'ensemble des données POST
	// retourne le résultat de validatePayment ou false
	public function processIpn(array $post) {

		if (!empty($post['txn_id']) && !empty($post['invoice'])) {

			// https://developer.paypal.com/docs/api-basics/notifications/ipn/IPNIntro/
			$response = $this->callApi(['url' => ($isTest = empty($post['test_ipn'])) ?
				'https://ipnpb.paypal.com/cgi-bin/webscr?cmd=_notify-validate&'.http_build_query($post) :
				'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr?cmd=_notify-validate&'.http_build_query($post)],
				Mage::app()->getStore()->getId(), $isTest, false);

			if ($response == ['VERIFIED']) {
				// pour le mode test
				$post['invoice'] = explode(':', $post['invoice']);
				$post['invoice'] = $post['invoice'][0];
				// charge la commande
				$order   = Mage::getModel('sales/order')->loadByIncrementId($post['invoice']);
				$payment = $order->getPayment();
				if (($order->getData('increment_id') == $post['invoice']) && in_array($payment->getData('method'), $this->_codes))
					return $payment->getMethodInstance()->validatePayment($post, true);
			}
		}

		return false;
	}
}