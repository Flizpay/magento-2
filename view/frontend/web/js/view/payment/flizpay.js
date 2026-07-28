define([
  "uiComponent",
  "Magento_Checkout/js/model/payment/renderer-list",
], function (Component, rendererList) {
  "use strict";

  rendererList.push({
    type: "flizpay",
    component: "Flizpay_Payment/js/view/payment/method-renderer/flizpay-method",
  });

  return Component.extend({});
});
