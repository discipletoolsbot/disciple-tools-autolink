import { DtButton } from "@disciple.tools/web-components";

/**
 * The form submit button.
 *
 * dt-button answers a submit click by dispatching a `submit` event at the form.
 * That notifies listeners but does not submit anything - only `requestSubmit()`
 * (or a real submit button) does - so with web components 1.0 the group form
 * silently did nothing. Submit it ourselves, and keep the original guard against
 * a double click posting the form twice.
 */
export class SubmitButton extends DtButton {
  handleClick(e) {
    e.preventDefault();

    const form = this.internals?.form;

    if (!form || this.clicked) {
      return;
    }

    // requestSubmit() runs the form's constraint validation and shows the
    // browser's validation UI, so only latch `clicked` once it will really post.
    if (!form.checkValidity()) {
      form.requestSubmit();
      return;
    }

    this.clicked = true;
    form.requestSubmit();
  }
}

window.customElements.define("submit-button", SubmitButton);
