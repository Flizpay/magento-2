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

  /**
   * Keep the checkout preview in sync with the Yes/No display toggles.
   *
   * @param {{fields: {logo: string, subtitle: string}}} config
   * @param {HTMLElement} element
   */
  function initialize(config, element) {
    var $preview = $(element);

    /**
     * Bind a Yes/No select to a preview visibility handler.
     *
     * @param {string} fieldId
     * @param {function(boolean): void} apply
     */
    function bind(fieldId, apply) {
      var $field = $("#" + fieldId);

      if (!$field.length) {
        return;
      }

      $field.on("change", function () {
        apply($field.val() === "1");
      });
      apply($field.val() === "1");
    }

    /**
     * Toggle the preview elements matching the given roles.
     *
     * @param {Array<string>} roles
     * @param {boolean} visible
     */
    function toggleRoles(roles, visible) {
      roles.forEach(function (role) {
        $preview.find('[data-role="' + role + '"]').toggle(visible);
      });
    }

    bind(config.fields.logo, function (enabled) {
      toggleRoles(["logo", "label-logo"], enabled);
    });
    bind(config.fields.subtitle, function (enabled) {
      toggleRoles(["subtitle", "label-subtitle"], enabled);
    });
  }

  return initialize;
});
