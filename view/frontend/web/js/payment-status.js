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
