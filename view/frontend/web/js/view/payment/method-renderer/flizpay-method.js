define(["Magento_Checkout/js/view/payment/default"], function (Component) {
  "use strict";

  return Component.extend({
    defaults: {
      template: "Flizpay_Payment/payment/flizpay",
    },

    redirectAfterPlaceOrder: false,

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
