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

  var labels = {
    not_connected: "Not connected",
    connecting: "Connecting",
    connected: "Connected",
    failed: "Connection failed",
  };

  /**
   * Poll the connection-status endpoint while the connection is being
   * established and update the Admin config field accordingly.
   *
   * @param {{url: string, status: string}} config
   * @param {HTMLElement} element
   */
  function initialize(config, element) {
    var attempts = 0;

    function poll() {
      $.getJSON(config.url)
        .done(function (response) {
          $(element).text(labels[response.status] || response.status);
          $("#flizpay-connection-verified").text(
            response.verifiedAt
              ? "Last verified: " + response.verifiedAt + " UTC"
              : "",
          );

          attempts += 1;
          if (response.status === "connecting" && attempts < 15) {
            window.setTimeout(poll, 2000);
          }
        })
        .fail(function () {
          attempts += 1;
          if (attempts < 15) {
            window.setTimeout(poll, 2000);
          }
        });
    }

    if (config.status === "connecting") {
      window.setTimeout(poll, 2000);
    }
  }

  return initialize;
});
