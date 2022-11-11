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

class Kyrena_Paymentmax_Model_Observer {

	// EVENT admin_system_config_changed_section_payment (adminhtml)
	public function clearConfig(Varien_Event_Observer $observer) {

		$database = Mage::getSingleton('core/resource');
		$write = $database->getConnection('core_write');
		$table = $database->getTableName('core_config_data');

		$payments = Mage::getModel('payment/config')->getAllMethods();
		foreach ($payments as $code => $payment) {

			if (Mage::getStoreConfigFlag('payment/account/remove_'.$code)) {

				$write->query('DELETE FROM '.$table.' WHERE path LIKE "payment/'.$code.'/%" AND path NOT LIKE "payment/'.$code.'/active"');
				$write->query('DELETE FROM '.$table.' WHERE path LIKE "payment/'.$code.'/active" AND scope_id != 0');

				Mage::getModel('core/config')->saveConfig('payment/'.$code.'/active', '0');
			}
		}

		Mage::getConfig()->reinit(); // très important
	}

	// EVENT adminhtml_init_system_config (adminhtml)
	public function hideConfig(Varien_Event_Observer $observer) {

		$section = Mage::app()->getRequest()->getParam('section');
		if ($section == 'payment') {
			$nodes    = $observer->getData('config')->getNode('sections/payment/groups')->children();
			$payments = Mage::getModel('payment/config')->getAllMethods();
			foreach ($payments as $code => $payment) {
				if (!empty($nodes->{$code}) && Mage::getStoreConfigFlag('payment/account/remove_'.$code)) {
					$nodes->{$code}->show_in_default = 0;
					$nodes->{$code}->show_in_website = 0;
					$nodes->{$code}->show_in_store = 0;
				}
			}
		}
	}

	// EVENT sales_quote_collect_totals_before (frontend)
	// EVENT sales_quote_save_before (frontend)
	public function updateCurrencyForOrder(Varien_Event_Observer $observer) {

		$quote   = $observer->getData('quote');
		$payment = $quote->getPayment();

		if (is_object($payment) && $payment->hasMethodInstance() && Mage::getStoreConfigFlag('payment/'.$payment->getMethodInstance()->getCode().'/allow_current_currency')) {

			$currency = $quote->getStore()->getCurrentCurrency();
			$code = $currency->getCode();

			if ($payment->getMethodInstance()->canUseForCurrency($code)) {
				Mage::app()->getStore()->setBaseCurrency($currency);
				$quote
					->setData('forced_currency', $currency)
					//->setData('global_currency_code', $code)
					->setData('base_currency_code', $code)
					->setData('store_currency_code', $code)
					->setData('store_to_base_rate', 1)
					->setData('store_to_quote_rate', 1)
					->setData('base_to_global_rate', 1)
					->setData('base_to_quote_rate', 1);
			}
		}
	}

	// CRON paymentmax_cancel_orders
	// all=true for old orders (without paymentmax)
	public function cancelPendingOrders($cron = null, $all = false) {

		// https://stackoverflow.com/a/33035088/2980105
		$limit = (int) str_replace(['G', 'M', 'K'], ['000000000', '000000', '000'], ini_get('memory_limit'));
		if ($limit < 4294967296)
			ini_set('memory_limit', '4G');

		$msg = [];
		$payments = Mage::getModel('payment/config')->getAllMethods();
		$storeIds = Mage::getResourceModel('core/store_collection')->getAllIds();
		$hasError = false;
		sort($storeIds);

		foreach ($storeIds as $storeId) {

			foreach ($payments as $code => $payment) {

				if (strncmp($code, 'paymentmax', 10) !== 0)
					continue;
				if (!method_exists($payment, 'getTransaction'))
					continue;
				if (!$payment->getConfigFlag('cancel_pending', $storeId))
					continue;

				$orders = Mage::getResourceModel('sales/order_collection')
					->addFieldToFilter('store_id', $storeId)
					->addFieldToFilter('state', ['in' => ['new', 'pending_payment']])
					->addFieldToFilter('updated_at', ['lt' => new Zend_Db_Expr('DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 MINUTE)')])
					->setPageSize($all ? 10000 : 1000)
					->setOrder('entity_id', 'asc');

				$orders->getSelect()->joinLeft(['payment' => 'sales_flat_order_payment'], 'payment.parent_id = main_table.entity_id', ['payment_method' => 'payment.method']);
				$orders->addFieldToFilter('payment.method', ($all === false) ? $code : ['in' => $payment->getAllCodes()]);

				foreach ($orders as $order) {

					try {
						$method = $order->getPayment()->getData('method');
						$payed  = $order->getPayment()->getMethodInstance()->askTransaction(true);

						if ($payed !== false) {
							$msg[] = $order->getData('increment_id').' ; '.$order->getData('created_at').' ; '.
								$order->getData('state').'/'.$order->getData('status').'/'.$method.' ; OOPS transaction found '.$payed;
						}
						else if ($order->canCancel() && ($order->getInvoiceCollection()->getSize() == 0) && ($order->getShipmentsCollection()->getSize() == 0)) {
							$order->load($order->getId());
							$order->cancel('Cancel pending order after 30 minutes.');
							$order->setData('status', 'canceled');
							$order->setData('state', 'canceled');
							$order->save();
							$msg[] = $order->getData('increment_id').' ; '.$order->getData('created_at').' ; '.
								$order->getData('state').'/'.$order->getData('status').'/'.$method.' ; now canceled';
						}
						else {
							$text  = 'Can not cancel pending order after 30 minutes.';
							$items = $order->getStatusHistoryCollection();
							foreach ($items as $item) {
								if ($item->getData('comment') == $text)
									$item->delete();
							}

							$order->addStatusHistoryComment($text)->save();
							$msg[] = $order->getData('increment_id').' ; '.$order->getData('created_at').' ; '.
								$order->getData('state').'/'.$order->getData('status').'/'.$method.' ; order is NOT cancelable';
						}
					}
					catch (Throwable $t) {
						$hasError = true;
						$msg[] = $order->getData('increment_id').' ; '.$method.' ERROR '.$t->getMessage();
					}
				}

				if (is_object($cron))
					$cron->setData('messages', implode("\n", $msg))->save();
			}
		}

		if (is_object($cron)) {
			$cron->setData('messages', 'memory: '.((int) (memory_get_peak_usage(true) / 1024 / 1024)).'M (max: '.ini_get('memory_limit').')'."\n".implode("\n", $msg));
			if ($hasError) // pour le statut du cron
				Mage::throwException('At least one error occurred while canceling orders.'."\n\n".$cron->getData('messages')."\n\n");
		}

		return $msg;
	}
}