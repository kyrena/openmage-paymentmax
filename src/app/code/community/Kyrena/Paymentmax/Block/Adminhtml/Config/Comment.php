<?php
/**
 * Created V/21/05/2021
 * Updated M/24/01/2023
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

class Kyrena_Paymentmax_Block_Adminhtml_Config_Comment extends Mage_Adminhtml_Block_System_Config_Form_Fieldset {

	protected $_html;

	public function render(Varien_Data_Form_Element_Abstract $element) {

		$html = [];
		$help = $this->helper('paymentmax');
		$code = (string) str_replace('payment_', '', $element->getHtmlId()); // (yes)

		$storeId    = $this->getStoreId();
		$comment    = $element->getComment();
		$comment    = empty($comment) ? '' : '<p>'.$comment.'</p>';
		$maxAmounts = [];
		$maxWeight  = [];

		$defaultCountry = Mage::getStoreConfig('general/country/default', $storeId);
		$allCountries   = Mage::getStoreConfig('payment/'.$code.'/allowedcountry');
		$allCountries   = empty($allCountries) ? [] : array_filter(explode(',', $allCountries));
		$selCountries   = $help->getPaymentCountries($code, $storeId);
		//$euCountries  = explode(',', Mage::getStoreConfig('general/country/eu_countries'));

		// devises du mode de paiement
		// $this->__('Allowed currencies (%d) for this method:')

		// pays du mode de paiement
		// fait la liste, si elle est vide, c'est que tous les pays sont autorisés
		if (!empty($allCountries) && ($selCountries != $allCountries)) {

			$html['all'][] = '<p><span>'.$this->__('Allowed countries (%d) for this method:', count($allCountries)).'</span></p>';
			$html['all'][] = '<ul>';

			foreach ($allCountries as $country) {

				$name = Mage::getModel('directory/country')->loadByCode($country)->getName();
				$key  = strtolower($country);

				if (!empty($maxAmounts[$key]['amount'])) {
					$max = $help->getNumber($maxAmounts[$key]['amount'], ['precision' => 2]);
					if ($country == $defaultCountry)
						$html['all'][$country] = '<li><strong title="'.addslashes($this->__('Default Country')).'">'.$this->getFlag($country).'&nbsp;'.$country.' - '.$name.'</strong> <em>(max '.$max.' '.$maxAmounts[$key]['currency'].')</em></li>';
					else
						$html['all'][$country] = '<li>'.$this->getFlag($country).'&nbsp;'.$country.' - '.$name.' <em>(max '.$max.' '.$maxAmounts[$key]['currency'].')</em></li>';
				}
				else if ($country == $defaultCountry) {
					$html['all'][$country] = '<li><strong title="'.addslashes($this->__('Default Country')).'">'.$this->getFlag($country).'&nbsp;'.$country.' - '.$name.'</strong></li>';
				}
				else {
					$html['all'][$country] = '<li>'.$this->getFlag($country).'&nbsp;'.$country.' - '.$name.'</li>';
				}

				//if (in_array($country, $euCountries)) {
				//	$key = count($html['all']) - 1;
				//}
			}

			ksort($html['all'], SORT_NATURAL);
			$html['all'][] = '</ul>';
		}
		else if (!empty($allCountries)) {
			$html['all'][] = '<p><span>'.$this->__('Allowed countries (%d) for this method:', count($allCountries)).'</span> '.
				'<img src="'.$this->getSkinUrl('images/sort-arrow-down.png').'" class="v-middle" /></p>';
		}
		else {
			$defCountries = array_filter(explode(',', Mage::getStoreConfig('general/country/allow', $storeId)));
			$html['all'][] = '<p><span>'.$this->__('Allowed countries (%d) for this method:', count($defCountries)).'</span>'.
				' <a href="'.$this->getUrl('*/*/*', ['section' => 'general', 'store' => $this->getRequest()->getParam('store'), 'website' => $this->getRequest()->getParam('website')]).'">'.$this->__('All Allowed Countries').'</a>'.'</p>';
		}

		// pays possibles pour les clients
		// fait la liste, si elle est vide, c'est qu'aucun pays n'est autorisé
		if (!empty($selCountries)) {

			$html['sel'][] = '<p><span>'.$this->__('Allowed countries (%d) for customers:', count($selCountries)).'</span></p>';
			$html['sel'][] = '<ul>';

			foreach ($selCountries as $country) {

				$name = Mage::getModel('directory/country')->loadByCode($country)->getName();
				$key  = strtolower($country);

				if (!empty($maxAmounts[$key]['amount'])) {
					$max = $help->getNumber($maxAmounts[$key]['amount'], ['precision' => 2]);
					if ($country == $defaultCountry)
						$html['sel'][$country] = '<li><strong title="'.addslashes($this->__('Default Country')).'">'.$this->getFlag($country).'&nbsp;'.$country.' - '.$name.'</strong> <em>(max '.$max.' '.$maxAmounts[$key]['currency'].')</em></li>';
					else
						$html['sel'][$country] = '<li>'.$this->getFlag($country).'&nbsp;'.$country.' - '.$name.' <em>(max '.$max.' '.$maxAmounts[$key]['currency'].')</em></li>';
				}
				else if ($country == $defaultCountry) {
					$html['sel'][$country] = '<li><strong title="'.addslashes($this->__('Default Country')).'">'.$this->getFlag($country).'&nbsp;'.$country.' - '.$name.'</strong></li>';
				}
				else {
					$html['sel'][$country] = '<li>'.$this->getFlag($country).'&nbsp;'.$country.' - '.$name.'</li>';
				}

				//if (in_array($country, $euCountries)) {
				//	$key = count($html['sel']) - 1;
				//}
			}

			ksort($html['sel'], SORT_NATURAL);
			$html['sel'][] = '</ul>';
		}
		else {
			$html['sel'][] = '<p><span>'.$this->__('Allowed countries (%d) for customers:', 0).'</span>'.
				' <a href="'.$this->getUrl('*/*/*', ['section' => 'general', 'store' => $this->getRequest()->getParam('store'), 'website' => $this->getRequest()->getParam('website')]).'">'.$this->__('None').'</a></p>';
		}

		// final
		$this->_html =
			'<div class="comment paymentmax">'.
				(empty($svg = Mage::getStoreConfig('payment/'.$code.'/img_backend')) ? '' : '<img src="'.$this->getSkinUrl('images/kyrena/paymentmax/'.$svg).'" alt="" class="paymentmax logo" />').
				$comment.
				'<div class="countries">'.
					(empty($html['all']) ? '' : implode($html['all'])).
					(empty($html['sel']) ? '' : implode($html['sel'])).
				'</div>'.
			'</div>';

		// drapeau et éventuel marquage
		$legend = $element->getLegend();
		$flag   = (preg_match('#^[A-Z]{2} - #', $legend) === 1) ? $this->getFlag(substr($legend, 0, 2)).'&nbsp;&nbsp;' : '';
		$element->setLegend($flag.$legend.
			((in_array($defaultCountry, $selCountries) && Mage::getStoreConfigFlag('payment/'.$code.'/active', $storeId)) ? ' *' : ''));

		return parent::render($element);
	}

	protected function _getHeaderCommentHtml($element) {
		return $this->_html ?? parent::_getHeaderCommentHtml($element);
	}

	protected function getFlag($code) {
		return mb_convert_encoding('&#'.(127397 + ord($code[0])).';', 'UTF-8', 'HTML-ENTITIES').
			mb_convert_encoding('&#'.(127397 + ord($code[1])).';', 'UTF-8', 'HTML-ENTITIES');
	}

	protected function getStoreId() {

		$store   = $this->getRequest()->getParam('store');
		$website = $this->getRequest()->getParam('website');

		if (!empty($store))
			$storeId = Mage::app()->getStore($store)->getId();
		else if (!empty($website))
			$storeId = Mage::getModel('core/website')->load($website)->getDefaultStore()->getId();
		else
			$storeId = Mage::app()->getDefaultStoreView()->getId();

		return $storeId;
	}
}