<?php
/**
 * Created V/21/05/2021
 * Updated M/05/07/2022
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

class Kyrena_Paymentmax_Block_Adminhtml_Config_Comment extends Mage_Adminhtml_Block_System_Config_Form_Fieldset {

	public function render(Varien_Data_Form_Element_Abstract $element) {

		// img.logo.paymentmax {
		//	float:right; display:inline-block; margin:0 0 0.5em 2em; max-width:200px; max-height:50px;
		//	image-rendering:optimizeQuality; image-rendering:-moz-crisp-edges;
		//}

		$html = [];
		$code = (string) str_replace('payment_', '', $element->getId()); // (yes)

		if (stripos($code, 'paymentmax_') === false) {
			$this->_html = null;
			return parent::render($element);
		}

		$storeId = $this->getStoreId();
		$comment = $element->getComment();
		$comment = empty($comment) ? '' : '<p>'.$comment.'</p>';

		$defaultCountry = Mage::getStoreConfig('general/country/default', $storeId);
		$allCountries   = Mage::getSingleton('paymentmax/payment_'.str_replace('paymentmax_', '', $code))->canUseForCountry(null, true);
		$selCountries   = $this->helper('paymentmax')->getPaymentCountries($code, $storeId);

		// pays du mode de paiement
		// fait la liste, si elle est vide, c'est que tous les pays sont autorisés
		if ($selCountries != $allCountries) {

			$html['all'][] = $this->__('Allowed countries for this method:').' ';
			foreach ($allCountries as $country) {

				$name = Mage::getModel('directory/country')->loadByCode($country)->getName();
				$key  = strtolower($country);

				if (!empty($maxAmounts[$key]['amount'])) {
					$max = $help->getNumber($maxAmounts[$key]['amount'], ['precision' => 2]);
					if ($country == $defaultCountry)
						$html['all'][] = '<u><span title="'.addslashes($this->__('Default Country')).' - '.$name.', max '.$max.' '.$maxAmounts[$key]['currency'].'">'.$country.'</span></u>';
					else
						$html['all'][] = '<span title="'.$name.', max '.$max.' '.$maxAmounts[$key]['currency'].'">'.$country.'</span>';
				}
				else if ($country == $defaultCountry) {
					$html['all'][] = '<u><span title="'.addslashes($this->__('Default Country')).' - '.$name.'">'.$country.'</span></u>';
				}
				else {
					$html['all'][] = '<span title="'.$name.'">'.$country.'</span>';
				}
			}

			if (empty($allCountries)) {
				$html['all'][] = '<a href="'.$this->getUrl('*/*/*', ['section' => 'general', 'store' => $this->getRequest()->getParam('store'), 'website' => $this->getRequest()->getParam('website')]).'">'.$this->__('All Allowed Countries').'</a>';
			}
		}

		// pays possibles pour les clients
		// fait la liste, si elle est vide, c'est qu'aucun pays n'est autorisé
		$html['sel'][] = $this->__('Allowed countries for customers:').' ';
		foreach ($selCountries as $country) {

			$name = Mage::getModel('directory/country')->loadByCode($country)->getName();
			$key  = strtolower($country);

			if (!empty($maxAmounts[$key]['amount'])) {
				$max = $help->getNumber($maxAmounts[$key]['amount'], ['precision' => 2]);
				if ($country == $defaultCountry)
					$html['sel'][] = '<u title="'.addslashes($this->__('Default Country')).'"><strong>'.$country.'</strong>&nbsp;<em>('.$name.', max '.$max.'&nbsp;'.$maxAmounts[$key]['currency'].')</em></u>';
				else
					$html['sel'][] = '<strong>'.$country.'</strong>&nbsp;<em>('.$name.', max '.$max.'&nbsp;'.$maxAmounts[$key]['currency'].')</em>';
			}
			else if ($country == $defaultCountry) {
				$html['sel'][] = '<u title="'.addslashes($this->__('Default Country')).'"><strong>'.$country.'</strong>&nbsp;<em>('.$name.')</em></u>';
			}
			else {
				$html['sel'][] = '<strong>'.$country.'</strong>&nbsp;<em>('.$name.')</em>';
			}
		}

		if (empty($selCountries)) {
			$html['sel'][] = '<a href="'.$this->getUrl('*/*/*', ['section' => 'general', 'store' => $this->getRequest()->getParam('store'), 'website' => $this->getRequest()->getParam('website')]).'">'.$this->__('None').'</a>';
		}

		// final
		$code = (string) str_replace('payment_', '', $element->getId()); // (yes)
		$this->_html = str_replace(': ,', ': ',
			'<div class="comment paymentmax">'.
				(empty($svg = Mage::getStoreConfig('payment/'.$code.'/img_backend')) ? '' : '<img src="'.$this->getSkinUrl('images/kyrena/paymentmax/'.$svg).'" alt="" class="paymentmax logo" style="float:right; display:inline-block; margin:0 0 0.5em 2em; max-width:150px; height:50px; image-rendering:optimizeQuality; image-rendering:-moz-crisp-edges;" />').
				$comment.
				(empty($html['all']) ? '' : '<p>'.implode(', ', $html['all']).'.</p>').
				(empty($html['sel']) ? '' : '<p>'.implode(', ', $html['sel']).'.</p>').
			'</div>');

		// drapeau utf8
		$flag = '';
		if (preg_match('#^[A-Z]{2} - #', $element->getData('legend')) === 1) {
			$flag = substr($element->getData('legend'), 0, 2);
			$flag = mb_convert_encoding('&#'.(127397 + ord($flag[0])).';', 'UTF-8', 'HTML-ENTITIES').
				mb_convert_encoding('&#'.(127397 + ord($flag[1])).';', 'UTF-8', 'HTML-ENTITIES').
				' &nbsp;';
		}

		// marquage
		$element->setLegend($flag.$element->getData('legend').((in_array($defaultCountry, $selCountries) && Mage::getStoreConfigFlag('payment/'.$code.'/active', $storeId)) ? ' *' : ''));

		return parent::render($element);
	}

	protected function _getHeaderCommentHtml($element) {
		return $this->_html ?? parent::_getHeaderCommentHtml($element);
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