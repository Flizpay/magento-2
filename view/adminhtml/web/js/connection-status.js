define(["require", "exports", "jquery"], function (require, exports, $) {
    "use strict";
    var labels = {
        not_connected: "Not connected",
        connecting: "Connecting",
        connected: "Connected",
        failed: "Connection failed",
    };
    function initialize(config, element) {
        var attempts = 0;
        function poll() {
            $.getJSON(config.url)
                .done(function (response) {
                var _a;
                $(element).text((_a = labels[response.status]) !== null && _a !== void 0 ? _a : response.status);
                $("#flizpay-connection-verified").text(response.verifiedAt
                    ? "Last verified: ".concat(response.verifiedAt, " UTC")
                    : "");
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
