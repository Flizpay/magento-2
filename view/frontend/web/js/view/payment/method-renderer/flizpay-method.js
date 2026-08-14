/**
 * FLIZpay Magento 2
 *
 * This Magento 2 extension enables to process payments with FLIZpay.
 *
 * @package FlizPay_Payment
 * @author  FLIZpay GmbH
 * @license OSL-3.0 (https://opensource.org/license/osl-3-0-php) / AFL-3.0 (https://opensource.org/license/afl-3-0-php)
 * @link    https://flizpay.de
 */

define(["Magento_Checkout/js/view/payment/default"], function (Component) {
  "use strict";

  return Component.extend({
    defaults: {
      template: "FlizPay_Payment/payment/flizpay",
    },

    redirectAfterPlaceOrder: false,

    getCashbackConfig: function () {
      return window.checkoutConfig.payment.flizpay.cashback || {};
    },

    getDisplayTitle: function () {
      return this.getCashbackConfig().title || this.getTitle();
    },

    getCashbackDescription: function () {
      return this.getCashbackConfig().description || "";
    },

    shouldShowDescription: function () {
      return (
        this.isChecked() === this.getCode() &&
        this.getCashbackDescription() !== ""
      );
    },

    shouldShowMoreInfo: function () {
      return (
        this.isChecked() === this.getCode() &&
        this.getCashbackConfig().available === true
      );
    },

    shouldShowLogo: function () {
      return this.getCashbackConfig().showLogo === true;
    },

    getLogoUrl: function () {
      return require.toUrl("FlizPay_Payment/images/fliz-checkout-logo.svg");
    },

    afterPlaceOrder: function () {
      var config = window.checkoutConfig.payment.flizpay,
        form = document.createElement("form"),
        formKey = document.createElement("input");

      form.method = "POST";
      form.action = config.startUrl;

      formKey.type = "hidden";
      formKey.name = "form_key";
      formKey.value = config.formKey;
      form.appendChild(formKey);

      document.body.appendChild(form);
      form.submit();
    },
  });
});
