import { DtNumberField } from "@disciple.tools/web-components";

/**
 * A dt-number that saves itself through the Autolink magic link endpoint.
 *
 * Tracks the current web component library: the save hook is `_change`
 * (the library's own change handler) and validation is `_validateValue`.
 */
export class ChurchHealthField extends DtNumberField {
  static get properties() {
    return {
      ...super.properties,
      nonce: { type: String },
    };
  }

  /**
   * Display an unset count as an empty input rather than a literal "0".
   * Delegates to the library's reactive accessor so updates still render.
   */
  get value() {
    return super.value === "0" ? "" : super.value;
  }

  set value(value) {
    super.value = value ? value : "0";
  }

  get action() {
    return window.app.rest_base + window.magic.rest_namespace;
  }

  async _change(e) {
    if (!this._validateValue(e.target.value)) {
      e.currentTarget.value = "";
      return;
    }

    const newValue = e.target.value;
    const oldValue = this.value;

    this.value = newValue;

    this.dispatchEvent(
      new CustomEvent("change", {
        detail: {
          field: this.name,
          oldValue,
          newValue,
        },
        bubbles: true,
        composed: true,
      })
    );

    const params = {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": this.nonce || window.app.nonce,
      },
      body: JSON.stringify({
        id: this.id,
        value: newValue,
        action: "update_field",
        parts: window.magic.parts,
      }),
    };

    try {
      const response = await fetch(this.action, params);
      const body = await response.json();

      if (body.data && body.data.status && body.data.status !== 200) {
        this.handleError(body.message);
      } else if (body.success == false) {
        this.handleError(body.data.message);
      }
    } catch (error) {
      this.handleError(error);
    }
  }

  handleError(error) {
    console.error(error);
  }
}

window.customElements.define("app-church-health-field", ChurchHealthField);
