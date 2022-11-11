<?php
/**
 * Created V/12/11/2021
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

require_once(Mage::getModuleDir('controllers', 'Kyrena_Paymentmax').'/PaymentmaxController.php');

class Kyrena_Paymentmax_TpayblikController extends Kyrena_Paymentmax_PaymentmaxController {

	protected $_code = 'paymentmax_tpayblik';

	public function waitingAction() {

		$session = Mage::getSingleton('checkout/session');
		if (empty($id = $session->getLastOrderId())) {
			$this->_redirect('checkout/cart');
		}
		else {
			$order = Mage::getModel('sales/order')->load($id);
			if (is_object($payment = $order->getPayment()) && ($payment->getData('method') == $this->_code)) {
				if ($order->hasInvoices()) {
					// commande payée par ipn (même si peu probable)
					if (empty($session->getLastSuccessQuoteId())) {
						Mage::getSingleton('checkout/cart')->truncate()->save();
						$this->_redirect('sales/order/view', ['order_id' => $order->getId()]);
					}
					else {
						$session->clearHelperData();
						$session->setLastQuoteId($order->getData('quote_id'))
							->setLastSuccessQuoteId($order->getData('quote_id'))
							->setLastOrderId($order->getId())
							->setLastRealOrderId($order->getData('increment_id'))
							->setRedirectUrl('');
						$this->_redirect('checkout/onepage/success');
					}
				}
				else if (!empty($newCode = $this->getRequest()->getPost('otp'))) {
					// réessaye avec un nouveau code
					$payment->setData('po_number', is_array($newCode) ? implode('', $newCode) : $newCode)->save();
					$this->_forward('redirect');
				}
				else {
					$result = $payment->getMethodInstance()->validatePayment([], false, true);
					// paiement accepté
					if ($result === true) {
						// vide le panier (le client a fait retour sur la page success)
						if (empty($session->getLastSuccessQuoteId())) {
							Mage::getSingleton('checkout/cart')->truncate()->save();
							$this->_redirect('sales/order/view', ['order_id' => $order->getId()]);
						}
						// affiche toujours au moins une fois la page d'attente
						// cela permettra de rediriger vers le détail de la commande lorsque le client fera retour sur la page success
						// pour le savoir on utilise cc_approval qui doit contenir l'id de la commande
						else if ($payment->getData('cc_approval') != $order->getId()) {
							$this->getResponse()->setBody(Mage::getBlockSingleton('core/template')
								->setTemplate('kyrena/paymentmax/tpayblik.phtml')
								->setData('action', 'checkout/onepage/success')
								->setData('valid', true)
								->setData('fast', true)
								->toHtml());
						}
						else {
							$this->_redirect('checkout/onepage/success');
						}
					}
					// affiche la page de saisie du code (si code faux) ou d'attente (si tpay n'a pas terminé)
					else if ($result == 'waiting') {
						$payment->setData('cc_approval', $order->getId())->save();
						$this->getResponse()->setBody(Mage::getBlockSingleton('core/template')
							->setTemplate('kyrena/paymentmax/tpayblik.phtml')
							->setData('valid', $payment->getData('cc_secure_verify') == 'valid')
							->setData('code', $payment->getData('po_number'))
							->setData('action', '*/*/*')
							->toHtml());
					}
					// paiement refusé (restaure le panier)
					else {
						$session->addError($this->__('Payment refused.'));
						$quote = Mage::getModel('sales/quote')->load($order->getData('quote_id'));
						if (!empty($quote->getId())) {
							$quote->setIsActive(1)->setReservedOrderId(null)->save();
							$session->replaceQuote($quote);
						}
						$this->_redirect('checkout/onepage');
					}
				}
			}
			else {
				$this->_redirect('checkout/cart');
			}
		}
	}
}