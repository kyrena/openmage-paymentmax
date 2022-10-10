<?php
/**
 * Created V/22/10/2021
 * Updated V/24/06/2022
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

class Kyrena_Paymentmax_Block_Adminhtml_Config_Countries extends Mage_Adminhtml_Block_System_Config_Form_Field {

	public function render(Varien_Data_Form_Element_Abstract $element) {

		$parts = explode('_', $element->getId());
		if (!$this->_options)
			$this->_options = Mage::getResourceModel('directory/country_collection')->loadData()->toOptionArray(false);

		$countries = Mage::getSingleton('paymentmax/payment_'.$parts[2])->canUseForCountry(null, true);
		$options = [];
		foreach ($this->_options as $option) {
			if (is_array($countries) && in_array($option['value'], $countries))
				$options[] = $option;
		}

		$element->setValues($options);
		return parent::render($element);
	}
}