<?php
/**
 * Created V/22/10/2021
 * Updated J/03/11/2022
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

abstract class Kyrena_Paymentmax_PaymentmaxController extends Mage_Core_Controller_Front_Action {

	public function preDispatch() {
		Mage::register('turpentine_nocache_flag', true, true);
		parent::preDispatch();
	}

	public function redirectAction() {

		$session = Mage::getSingleton('checkout/session');
		if (empty($id = $session->getLastOrderId())) {
			$this->_redirect('checkout/cart');
		}
		else {
			$order = Mage::getModel('sales/order')->load($id);
			if (is_object($payment = $order->getPayment()) && ($payment->getData('method') == $this->_code)) {
				// redirige
				$response = $payment->getMethodInstance()->redirectToPayment();
				if (strncasecmp($response, 'http', 4) === 0)
					$this->_redirectUrl($response);
				else
					$this->getResponse()->setBody($response);
			}
			else {
				$this->_redirect('checkout/cart');
			}
		}
	}

	public function returnAction() {

		$session = Mage::getSingleton('checkout/session');
		if (empty($id = $session->getLastOrderId())) {
			$this->_redirect('checkout/cart');
		}
		else {
			$order = Mage::getModel('sales/order')->load($id);
			if (is_object($payment = $order->getPayment()) && ($payment->getData('method') == $this->_code)) {
				$result = $payment->getMethodInstance()->validatePayment($this->getRequest()->getParams());
				if ($result === true) {
					if ($session->getLastSuccessQuoteId()) {
						// paiement accepté
						$this->_redirect('checkout/onepage/success');
					}
					else {
						// vide le panier (le client a fait retour sur la page success)
						Mage::getSingleton('checkout/cart')->truncate()->save();
						$this->_redirect('sales/order/view', ['order_id' => $order->getId()]);
					}
				}
				else {
					// restaure le panier
					$session->addError(($result === false) ? $this->__('Payment canceled.') : $this->__('Payment refused.'));
					$quote = Mage::getModel('sales/quote')->load($order->getData('quote_id'));
					if (!empty($quote->getId())) {
						$quote->setIsActive(1)->setReservedOrderId(null)->save();
						$session->replaceQuote($quote);
					}
					$this->_redirect('checkout/onepage');
				}
			}
			else {
				$this->_redirect('checkout/cart');
			}
		}
	}

	public function cancelAction() {

		$session = Mage::getSingleton('checkout/session');
		if (empty($id = $session->getLastOrderId())) {
			$this->_redirect('checkout/cart');
		}
		else {
			$order = Mage::getModel('sales/order')->load($id);
			if (is_object($payment = $order->getPayment()) && ($payment->getData('method') == $this->_code)) {
				// annule la commande
				$order->{$order->canCancel() ? 'cancel' : 'addStatusHistoryComment'}('Customer is back. Payment canceled by customer.')->save();
				// restaure le panier
				$session->addError($this->__('Payment canceled.'));
				$quote = Mage::getModel('sales/quote')->load($order->getData('quote_id'));
				if (!empty($quote->getId())) {
					$quote->setIsActive(1)->setReservedOrderId(null)->save();
					$session->replaceQuote($quote);
				}
				$this->_redirect('checkout/onepage');
			}
			else {
				$this->_redirect('checkout/cart');
			}
		}
	}

	public function ipnAction() {

		// body
		$data = file_get_contents('php://input');
		if (!empty($data))
			$data = @json_decode($data, true);

		// post
		if (empty($data))
			$data = $this->getRequest()->getPost();

		// action
		if (!empty($data))
			Mage::getModel('paymentmax/payment_'.str_replace('paymentmax_', '', $this->_code))->processIpn($data);

		$this->getResponse()
			->setHttpResponseCode(200)
			->setHeader('Content-Type', 'text/plain; charset=utf-8', true)
			->setHeader('Cache-Control', 'no-cache, must-revalidate', true)
			->setBody('ipn');
	}
}