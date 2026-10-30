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
   * @param {{fields: {headline: string, logo: string, description: string},
   *          component: {publicId: string, locale: string, amount: number}}} config
   * @param {HTMLElement} element
   */
  function initialize(config, element) {
    var $preview = $(element),
      component = config.component || {};

    /**
     * Build one hosted offer-text surface.
     *
     * What it shows is decided by FLIZpay; the preview only passes the shop's
     * public id, the admin locale, and the sample basket.
     *
     * @param {string} surface
     * @return {HTMLElement}
     */
    function createSurface(surface) {
      var fliz = document.createElement("fliz-checkout");

      fliz.dataset.publicId = component.publicId;
      fliz.dataset.surface = surface;
      fliz.dataset.locale = component.locale;
      fliz.dataset.amount = String(component.amount);

      // The shop's own name shows until the hosted script upgrades the element.
      if (surface === "title") {
        fliz.textContent = "FLIZpay";
      }

      return fliz;
    }

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

    bind(config.fields.headline, function (enabled) {
      var $title = $preview.find('[data-role="title"]');

      if (!component.publicId) {
        return;
      }

      // Replacing only the headline keeps the description element mounted, so
      // it never refetches its offer text.
      $title.empty();
      if (enabled) {
        $title.append(createSurface("title"));
      } else {
        $title.text("FLIZpay");
      }
      toggleRoles(["label-headline"], enabled);
    });

    bind(config.fields.logo, function (enabled) {
      toggleRoles(["logo", "label-logo"], enabled);
    });

    bind(config.fields.description, function (enabled) {
      toggleRoles(["description", "label-description"], enabled);
    });
  }

  return initialize;
});
