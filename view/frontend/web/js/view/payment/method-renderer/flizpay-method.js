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

  /**
   * Convert Magento's quote totals to the integer cents the component takes.
   *
   * <fliz-checkout> formats those cents as euros, so other currencies send no
   * amount at all and get the offer text without a sample saving. An absent
   * amount is therefore a valid result rather than an error.
   *
   * @param {Object|null} totals
   * @return {number|undefined}
   */
  function amountInCents(totals) {
    if (!totals || totals.quote_currency_code !== "EUR") {
      return undefined;
    }

    var cents = Math.round(Number(totals.grand_total) * 100);

    return Number.isSafeInteger(cents) && cents >= 0 ? cents : undefined;
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
      this._super();

      var config = window.checkoutConfig.payment.flizpay.checkout || {},
        // Without a public id the element has nothing to fetch, so the plain
        // payment method name stays.
        publicId = config.publicId || "",
        locale = config.locale || "";

      this.showHeadline = publicId !== "" && config.showHeadline === true;
      this.showDescription = publicId !== "" && config.showDescription === true;
      this.showLogo = config.showLogo === true;

      // quote.totals() is refreshed by every total-affecting change, so the
      // amount follows quantity, coupon and shipping updates on its own.
      // Both surfaces share these attributes, and mutating them on a mounted
      // element is what keeps it from refetching its offer text.
      this.componentAttrs = ko.computed(function () {
        return {
          "data-public-id": publicId,
          "data-locale": locale,
          "data-amount": amountInCents(quote.totals()),
        };
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
