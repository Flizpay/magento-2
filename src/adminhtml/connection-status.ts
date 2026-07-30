import $ = require("jquery");

import {
  ConnectionStatusConfig,
  ConnectionStatusResponse,
} from "@/types/index";

const labels: Record<string, string> = {
  not_connected: "Not connected",
  connecting: "Connecting",
  connected: "Connected",
  failed: "Connection failed",
};

function initialize(
  config: ConnectionStatusConfig,
  element: HTMLElement,
): void {
  let attempts = 0;

  function poll(): void {
    $.getJSON(config.url)
      .done((response: ConnectionStatusResponse) => {
        $(element).text(labels[response.status] ?? response.status);
        $("#flizpay-connection-verified").text(
          response.verifiedAt
            ? `Last verified: ${response.verifiedAt} UTC`
            : "",
        );

        attempts += 1;
        if (response.status === "connecting" && attempts < 15) {
          window.setTimeout(poll, 2000);
        }
      })
      .fail(() => {
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

export = initialize;
