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

define([
  "ko",
  "Magento_Checkout/js/view/payment/default",
  "Magento_Checkout/js/model/quote",
], function (ko, Component, quote) {
  "use strict";

  /** <fliz-checkout> formats integer cents as euros. */
  var COMPONENT_CURRENCY = "EUR",
    MAX_SAFE_CENTS = 9007199254740991;

  /**
   * Convert Magento's quote totals to the integer cents the component takes.
   *
   * Other currencies send no amount at all and get the offer text without a
   * sample saving, so an absent amount is a valid result rather than an error.
   *
   * @param {Object|null} totals
   * @return {number|undefined}
   */
  function amountInCents(totals) {
    if (!totals || totals.quote_currency_code !== COMPONENT_CURRENCY) {
      return undefined;
    }

    var total = Number(totals.grand_total),
      cents;

    if (!isFinite(total) || total < 0) {
      return undefined;
    }

    cents = Math.round(total * 100);

    return cents <= MAX_SAFE_CENTS ? cents : undefined;
  }

  return Component.extend({
    defaults: {
      template: "FlizPay_Payment/payment/flizpay",
    },

    redirectAfterPlaceOrder: false,

    /**
     * Expose the component inputs the server already resolved.
     *
     * The display toggles never change within a page, so the template renders
     * each surface once and only mutates its attributes afterwards. Remounting
     * the element would make it refetch its offer text.
     *
     * @return {Object}
     */
    initialize: function () {
      var config;

      this._super();

      config = window.checkoutConfig.payment.flizpay.checkout || {};

      // Without a public id the element has nothing to fetch, so the plain
      // payment method name stays.
      this.publicId = config.publicId || "";
      this.locale = config.locale || "";
      this.showHeadline =
        this.publicId !== "" && config.showHeadline === true;
      this.showDescription =
        this.publicId !== "" && config.showDescription === true;
      this.showLogo = config.showLogo === true;

      // quote.totals() is refreshed by every total-affecting change, so the
      // amount follows quantity, coupon and shipping updates on its own.
      this.amount = ko.computed(function () {
        return amountInCents(quote.totals());
      });

      return this;
    },

    /**
     * Whether the offer description should be visible right now.
     *
     * @return {boolean}
     */
    isDescriptionVisible: function () {
      return this.isChecked() === this.getCode();
    },

    /**
     * Return the FLIZpay checkout logo URL.
     *
     * @return {string}
     */
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
