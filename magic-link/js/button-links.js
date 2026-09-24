/**
 * Restore link behaviour for `<dt-button href="...">`.
 *
 * dt-button carried `href` and `confirm` properties through web components 0.x and
 * rendered an anchor when given one. 1.0 removed both and made the component a plain
 * `<button>` whose click handler calls `preventDefault()` unconditionally, so every
 * template that uses a button as a link stopped navigating.
 *
 * Wrapping the call sites in an `<a>` does not help - the component's own
 * `preventDefault()` cancels the default action for the whole event, the anchor's
 * navigation included. So listen for the click once it has left the component and
 * navigate ourselves. Keeping it here rather than rewriting the templates also keeps
 * `dt-button` styling (`css/_buttons.css`) applying to these buttons.
 */
export default function buttonLinks() {
  document.addEventListener("click", (event) => {
    if (typeof event.target.closest !== "function") {
      return;
    }

    const button = event.target.closest("dt-button[href]");

    if (!button || button.hasAttribute("disabled")) {
      return;
    }

    const href = button.getAttribute("href");

    if (!href) {
      return;
    }

    const message = button.getAttribute("confirm");

    // eslint-disable-next-line no-alert
    if (message && !window.confirm(message)) {
      return;
    }

    window.location.assign(href);
  });
}
