<?php
/**
 * Created V/22/10/2021
 * Updated S/14/01/2023
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

abstract class Kyrena_Paymentmax_Model_Payment extends Mage_Payment_Model_Method_Abstract {

	protected $_allowedCountries  = false; // canUseForCountry
	protected $_allowedCurrencies = false; // canUseForCurrency

	protected $_isGateway                  = true;
	protected $_canOrder                   = true;
	protected $_canAuthorize               = false;
	protected $_canCapture                 = false;
	protected $_canCapturePartial          = false;
	protected $_canCaptureOnce             = false;
	protected $_canRefund                  = true;
	protected $_canRefundInvoicePartial    = true;
	protected $_canVoid                    = false;
	protected $_canUseInternal             = false;
	protected $_canUseCheckout             = true;
	protected $_canUseForMultishipping     = false;
	protected $_isInitializeNeeded         = false;
	protected $_canFetchTransactionInfo    = false;
	protected $_canReviewPayment           = false;
	protected $_canCreateBillingAgreement  = false;
	protected $_canManageRecurringProfiles = false;

	// openmage
	public function refund(Varien_Object $payment, $amount) {

		$order     = $payment->getOrder();
		$captureId = $payment->getData('parent_transaction_id') ?? $payment->getData('last_trans_id'); // _lookupTransaction

		if (empty($amount) || ($amount <= 0)) {
			Mage::throwException('Can not refund 0 or less.');
		}
		else if (!empty($captureId)) {

			$canRefundMore = $order->canCreditmemo();
			$partial  = $canRefundMore || ($order->getData('base_total_online_refunded') > 0) || ($order->getData('base_total_offline_refunded') > 0);
			$response = $this->askRefund($order, $captureId, $amount, $partial);

			if ($response['status'] == 'success') {
				$payment->setData('transaction_id', $response['refundId']);
				$payment->setData('is_transaction_closed', true); // refund initiated by merchant
				$payment->setData('should_close_parent_transaction', !$canRefundMore);
				if (!empty($response['raw_details']))
					$payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $response['raw_details']);
			}
			else {
				Mage::throwException('Refund status: '.$response['status']);
			}
		}
		else {
			Mage::throwException(Mage::helper('paymentmax')->__('Impossible to issue a refund transaction because the capture transaction does not exist.'));
		}

		return $this;
	}

	public function getConfigData($field, $storeId = null) {

		if (isset($this->_allcnf) && ($field == 'active') && !in_array($this->_code, $this->_allcnf))
			return false;

		return parent::getConfigData($field, $storeId);
	}

	public function canUseForCountry($country) {

		if (is_bool($this->_allowedCountries)) {
			$this->_allowedCountries = array_filter(explode(',', (string) $this->getConfigData('allowedcountry')));
			if (empty($this->_allowedCountries))
				$this->_allowedCountries = array_filter(explode(',', Mage::getStoreConfig('general/country/allow', $this->getStore())));
		}

		// allowedcountry
		if (!in_array($country, $this->_allowedCountries))
			return false;

		// allowspecific + specificcountry
		if ($this->getConfigFlag('allowspecific')) {
			$countries = explode(',', $this->getConfigData('specificcountry'));
			if (!in_array($country, $countries))
				return false;
		}

		return true;
	}

	public function canUseForCurrency($currency) {

		if (is_bool($this->_allowedCurrencies)) {
			$this->_allowedCurrencies = array_filter(explode(',', (string) $this->getConfigData('allowedcurrency')));
			if (empty($this->_allowedCurrencies))
				return true;
		}

		if ($this->getConfigFlag('allow_current_currency') && (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[1]['function'] == 'isApplicableToQuote'))
			$currency = Mage::app()->getStore($this->getStore())->getCurrentCurrency()->getCode();

		return in_array($currency, $this->_allowedCurrencies);
	}

	public function getOrderPlaceRedirectUrl() {
		return Mage::getUrl('paymentmax/'.str_replace('paymentmax_', '', $this->_code).'/redirect', ['_secure' => true]);
	}

	// kyrena
	public function getAllCodes() {
		return $this->_codes ?? [$this->_code];
	}

	public function getOrderWaitingUrl(array $params = []) {
		return Mage::getUrl('paymentmax/'.str_replace('paymentmax_', '', $this->_code).'/waiting', array_merge(['_secure' => true], $params));
	}

	public function getOrderReturnUrl(array $params = []) {
		return Mage::getUrl('paymentmax/'.str_replace('paymentmax_', '', $this->_code).'/return', array_merge(['_secure' => true], $params));
	}

	public function getOrderIpnUrl(array $params = []) {
		return Mage::getUrl('paymentmax/'.str_replace('paymentmax_', '', $this->_code).'/ipn', array_merge(['_secure' => true], $params));
	}

	public function getConfigFlag($field, $storeId = null) {

		if ($storeId === null)
			$storeId = $this->getStore();

		$path = 'payment/'.$this->getCode().'/'.$field;
		return Mage::getStoreConfigFlag($path, $storeId);
	}

	public function redirectToPayment() {

		$order = $this->getInfoInstance()->getOrder();

		// si la commande est déjà payée
		if ($order->getTotalDue() <= 0.01)
			return Mage::getSingleton('checkout/session')->getLastSuccessQuoteId() ?
				Mage::getUrl('checkout/onepage/success') : Mage::getUrl('sales/order/view', ['order_id' => $order->getId()]);

		return $this->askRedirect($order);
	}

	protected function refundOrder(object $order, float $amount, string $currency, string $captureId, string $refundId, array $info = []) {

		$found   = false;
		$amount  = (float) abs($amount);
		$payment = $order->getPayment();

		// full or partial refund
		$refunds = $order->getCreditmemosCollection();
		foreach ($refunds as $refund) {
			if ($refund->getData('transaction_id') == $refundId) {
				$order->addStatusHistoryComment($this->getCommonMessage(true, $refundId).' Refund occurred ('.$amount.' '.$currency.'). Order is already refunded (by txnId).')->save();
				$found = true;
				break;
			}
		}

		// full refund
		if (!$found && (bccomp($order->getData('grand_total'), $amount, 2) == 0) && (bccomp($order->getData('total_refunded'), $amount, 2) == 0)) {
			$order->addStatusHistoryComment($this->getCommonMessage(true, $refundId).' Refund occurred ('.$amount.' '.$currency.'). Order is already fully refunded (by total).')->save();
			$found = true;
		}

		if (!$found) {

			$isHolded = $order->getData('status') == 'holded';
			if ($isHolded)
				$order->unhold();
			if ($this->uncancelOrder($order))
				$order->save();

			if ($order->canCreditmemo()) {

				// full refund
				if (bccomp($order->getData('grand_total'), $amount, 2) == 0) {

					$order->addStatusHistoryComment($this->getCommonMessage(true, $refundId).' Full refund occurred ('.$amount.' '.$currency.').')->save();

					$payment->setData('transaction_id', $refundId);
					$payment->setData('parent_transaction_id', $captureId);
					$payment->setData('is_transaction_closed', true); // refund initiated by platform
					$payment->setData('should_close_parent_transaction', true); // full refund

					if (!empty($info))
						$payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $info);

					$payment->registerRefundNotification($amount);
					Mage::dispatchEvent('sales_order_payment_refund', ['payment' => $payment, 'creditmemo' => $payment->getCreatedCreditmemo()]);

					if (is_object($payment->getCreatedCreditmemo()))
						$order->setData('state', 'closed')->setData('status', 'closed')->addStatusHistoryComment('', 'closed');

					Mage::getModel('core/resource_transaction')
						->addObject($payment)
						->addObject($order)
						->save();

					if (is_object($payment->getCreatedCreditmemo()))
						$payment->getCreatedCreditmemo()->sendEmail(true, '')->save();

					$found = true;
				}
				// partial refund
				else {
					$refunds = $order->getCreditmemosCollection();
					foreach ($refunds as $refund) {
						if (bccomp($refund->getData('grand_total'), $amount, 2) == 0) {
							$order->addStatusHistoryComment($this->getCommonMessage(true, $refundId).' Refund occurred ('.$amount.' '.$currency.'). Order is already refunded (by amount).')->save();
							$found = true;
							break;
						}
					}

					if (!$found) {
						$order->addStatusHistoryComment($this->getCommonMessage(true, $refundId).' Refund occurred ('.$amount.' '.$currency.'). You must create a partial refund.')->save();
					}
				}
			}
			else {
				$order->addStatusHistoryComment($this->getCommonMessage(true, $refundId).' Refund occurred ('.$amount.' '.$currency.'). Order is not refundable or is already refunded.')->save();
			}

			if ($isHolded && $order->canHold())
				$order->hold()->save();
		}

		return $found;
	}

	protected function uncancelOrder(object $order, bool $force = false) {

		if ($force || ($order->getData('state') == 'canceled') || ($order->getData('status') == 'canceled')) {

			if (($order->getTotalInvoiced() > 0) || ($order->getBaseTotalInvoiced() > 0)) {
				$order->setData('state', 'processing');
				$order->setData('status', 'processing');
				$order->setBaseTotalDue(null);
				$order->setTotalDue(null);
			} else {
				$order->setData('state', 'new');
				$order->setData('status', 'pending');
				$order->setBaseTotalDue($order->getBaseGrandTotal());
				$order->setTotalDue($order->getGrandTotal());
			}

			$order->setBaseDiscountCanceled(null);
			$order->setBaseShippingCanceled(null);
			$order->setBaseSubtotalCanceled(null);
			$order->setBaseTaxCanceled(null);
			$order->setBaseTotalCanceled(null);
			$order->setDiscountCanceled(null);
			$order->setShippingCanceled(null);
			$order->setSubtotalCanceled(null);
			$order->setTaxCanceled(null);
			$order->setTotalCanceled(null);

			foreach ($order->getAllItems() as $item) {

				$item->setQtyCanceled(null);
				$item->setTaxCanceled(null);
				$item->setHiddenTaxCanceled(null);

				// https://magento.stackexchange.com/a/182442/101197
				$item->setTotalQty($item->getQtyOrdered());
				$item->setQty($item->getQtyOrdered());
				$item->setTypeId($item->getProductType());
			}

			$event = new Varien_Event_Observer(['event' => new Varien_Object(['order' => $order, 'quote' => $order])]);
			Mage::getSingleton('cataloginventory/observer')->subtractQuoteInventory($event)->reindexQuoteInventory($event);
			Mage::getSingleton('downloadable/observer')->setLinkStatus($event);
			$order->addStatusHistoryComment('Undo order cancel.');
			$order->save();

			return true;
		}

		return false;
	}

	protected function getCommonMessage($ipn, $txnId = null) {
		return $ipn ? (empty($txnId) ? 'IPN.' : 'IPN (txnId: '.$txnId.').') : 'Customer is back.';
	}
}