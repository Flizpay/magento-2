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

define(["jquery"], function ($) {
  "use strict";

  return function (config, element) {
    var attempts = 0;
    var status = $(element).find('[data-role="flizpay-payment-status"]');

    function poll() {
      $.getJSON(config.statusUrl)
        .done(function (response) {
          if (response.status === "complete") {
            var separator = config.successUrl.indexOf("?") === -1 ? "?" : "&";
            window.location.assign(
              config.successUrl + separator + "_=" + Date.now(),
            );
            return;
          }

          attempts += 1;
          if (attempts < config.attempts) {
            window.setTimeout(poll, config.interval);
            return;
          }

          status.text(
            "Confirmation is taking longer than expected. You may refresh this page shortly.",
          );
        })
        .fail(function () {
          attempts += 1;
          if (attempts < config.attempts) {
            window.setTimeout(poll, config.interval);
          }
        });
    }

    window.setTimeout(poll, config.interval);
  };
});
