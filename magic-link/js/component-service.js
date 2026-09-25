import { ComponentService } from "@disciple.tools/web-components";

/**
 * Wire up the web component library's page level service.
 *
 * Several components do not talk to the API themselves - they dispatch an event
 * and wait for the page to answer. `dt-location-map` dispatches `dt:geocode` for
 * its address search; `dt-connection`, `dt-tags` and friends dispatch
 * `dt:get-data` to load their options. With nothing listening they just come up
 * empty, which is what the location search did.
 *
 * This is the same initialisation the theme does on its own screens (see
 * `dt-assets/js/new-record.js`). The post id is deliberately empty: `initialize()`
 * only turns on auto-save when it has one, and Autolink's forms post on submit
 * through the magic link endpoint rather than writing each field straight to the
 * DT posts API.
 */
export default function componentService() {
  if (!ComponentService) {
    return;
  }

  const service = new ComponentService(
    "groups",
    "",
    window.app?.nonce,
    window.app?.rest_base
  );

  service.initialize();

  // The theme exposes the instance the same way, so page specific scripts can
  // reach it without building a second one.
  window.componentService = service;

  return service;
}
