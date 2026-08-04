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
