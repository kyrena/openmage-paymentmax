<?php
/**
 * Created V/22/10/2021
 * Updated J/02/03/2023
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

class Kyrena_Paymentmax_Block_Adminhtml_Config_Help extends Mage_Adminhtml_Block_Abstract implements Varien_Data_Form_Element_Renderer_Interface {

	public function render(Varien_Data_Form_Element_Abstract $element) {

		if (str_contains($element->getHtmlId(), 'openmage'))
			return sprintf('<p class="box" style="margin-top:16px;">%s</p>', $element->getLegend());

		$msg = $this->checkRewrites();
		if ($msg !== true)
			return sprintf('<p class="box">%s %s <span class="right">Stop russian war. <b>🇺🇦 Free Ukraine!</b> | <a href="https://www.%s">github.com/kyrena</a></span></p><p class="box" style="margin-top:-5px; color:white; background-color:#E60000;"><strong>%s</strong><br />%s</p>',
				'Kyrena/Paymentmax', $this->helper('paymentmax')->getVersion(), 'github.com/kyrena/openmage-paymentmax',
				$this->__('INCOMPLETE MODULE INSTALLATION'),
				$this->__('There is conflict (<em>%s</em>).', $msg));

		$var = (int) ini_get($name = 'max_input_vars');
		$rav = (int) ini_get($eman = 'suhosin.post.max_vars');
		if (($rav > 0) && ($rav < $var)) {
			$name = $eman;
			$var = $rav;
		}
		$rav = (int) ini_get($eman = 'suhosin.request.max_vars');
		if (($rav > 0) && ($rav < $var)) {
			$name = $eman;
			$var = $rav;
		}

		return sprintf('<p class="box">%s %s <span class="no-display" id="inptvars"></span> <span class="right">Stop russian war. <b>🇺🇦 Free Ukraine!</b> | <a href="https://%s">github.com/kyrena</a></span></p>%s',
			'Kyrena/Paymentmax', $this->helper('paymentmax')->getVersion(), 'github.com/kyrena/openmage-paymentmax',
			'<script type="text/javascript">self.addEventListener("load", function () {'.
			' var nb = document.querySelectorAll("input, select, textarea").length, elem = document.getElementById("inptvars");'.
			' if ('.$var.' <= nb) {'.
			'  elem.innerHTML = " | ⚠ php:'.$name.' = '.$var.' <= " + nb + " inputs";'.
			'  elem.setAttribute("class", "error");'.
			' }'.
			'});</script>');
	}

	protected function checkRewrites() {

		$rewrites = [
			['helper' => 'payment/data'],
		];

		foreach ($rewrites as $rewrite) {
			foreach ($rewrite as $type => $class) {
				if (($type == 'model') && (mb_stripos(Mage::getConfig()->getModelClassName($class), 'kyrena') === false))
					return $class;
				else if (($type == 'block') && (mb_stripos(Mage::getConfig()->getBlockClassName($class), 'kyrena') === false))
					return $class;
				else if (($type == 'helper') && (mb_stripos(Mage::getConfig()->getHelperClassName($class), 'kyrena') === false))
					return $class;
			}
		}

		return true;
	}
}