Stop russian war. **🇺🇦 Free Ukraine!**

# paymentmax

A module to add new payment methods for [OpenMage](https://github.com/OpenMage/magento-lts).

Composer requirements:
* [tpay-com/tpay-php](https://github.com/tpay-com/tpay-php) (only for Tpay)
* [yoomoney/yookassa-sdk-php](https://github.com/yoomoney/yookassa-sdk-php) (only for YooKassa)

## New configuration options

In **System / Configuration / Payment Methods / General**, you can _hide and clear configuration_ for a custom selection of unused payment methods. You are seeing a `*` in section head? This is a mark to inform you that the payment method is available for the default country of the current store view.

## New payment methods

| Name | Logo/Link | Info |
| ---- | ---- | ---- |
| **PayPal** | [<img src="src/skin/frontend/base/default/images/kyrena/paymentmax/ic-logo-paypal.svg?raw=true" alt="" width="150" height="50"/>](https://www.paypal.com/) | |
| **Tpay** | [<img src="src/skin/frontend/base/default/images/kyrena/paymentmax/ic-logo-tpay.svg?raw=true" alt="" width="150" height="50"/>](https://www.tpay.com/) | |
| **YooKassa** | [<img src="src/skin/frontend/base/default/images/kyrena/paymentmax/ic-logo-yookassa.svg?raw=true" alt="" width="150" height="50"/>](https://yookassa.ru/) | don't work with this country, it's an enemy of your freedom |

## Copyright and Credits

- Current version: 1.0.1-beta (11/11/2022)
- Compatibility: OpenMage 19.x / 20.x / 21.x, PHP 7.2 / 7.3 / 7.4 / 8.0 / 8.1
- Client compatibility: Firefox 36+, Chrome 32+, Opera 19+, Edge 16+, Safari 9+
- Translations: English (en), French (fr-FR/fr-CA), German (de), Italian (it), Portuguese (pt-PT/pt-BR), Spanish (es) / Chinese (zh), Czech (cs), Dutch (nl), Greek (el), Hungarian (hu), Japanese (ja), Polish (pl), Romanian (ro), Russian (ru), Slovak (sk), Turkish (tr), Ukrainian (uk)
- License: GNU GPL 2+

If you like, take some of your time to improve the translations, go to https://bit.ly/2HyCCEc.

## Installation

First, remove Mage_Paypal and Mage_PaypalUk:
```
rm -r app/code/core/Mage/PaypalUk
rm -r app/code/core/Mage/Paypal
rm -r app/design/adminhtml/default/default/template/paypal
rm -r app/design/frontend/base/default/template/paypal
rm -r app/design/frontend/rwd/default/template/paypal
rm -f app/locale/*/Mage_Paypal.csv app/locale/*/Mage_Paypaluk.csv
rm -f app/design/frontend/base/default/layout/paypaluk.xml
rm -f app/design/frontend/base/default/layout/paypal.xml
rm -f app/design/frontend/rwd/default/layout/paypal.xml
rm -r skin/adminhtml/default/default/images/paypal*
rm -f skin/frontend/rwd/default/scss/module/_paypal.scss
```
```sql
DELETE FROM core_config_data WHERE path LIKE "payment/paypal%"
 OR path LIKE "paypal/%" OR path LIKE "paypalrefund/%";
```

Then, with composer:
- `composer require kyrena/openmage-paymentmax [--ignore-platform-reqs]`
- clear cache

Or, without composer:
- download latest [release](https://github.com/kyrena/openmage-paymentmax/releases) and extract _src/*_ directories
- clear cache
