import { css, html } from "lit";
import { DtBase } from "@disciple.tools/web-components";

/**
 * A link, collapsed to a button.
 *
 * <dt-copy-text> shows the whole URL in a field, which is a lot of card for
 * something nobody reads - a magic key is 64 characters of noise. This is the
 * same gesture in one button: press it, the link is on the clipboard, the icon
 * confirms it for a moment and goes back.
 */
export class AppCopyLink extends DtBase {
  static get properties() {
    return {
      value: { type: String },
      label: { type: String },
      copiedLabel: { type: String },
      title: { type: String },
      icon: { type: String },
      copied: { type: Boolean, attribute: false },
    };
  }

  constructor() {
    super();
    this.value = "";
    this.label = "";
    this.copiedLabel = "";
    this.icon = "mdi:link-variant";
    this.copied = false;
    this._resetTimer = null;
  }

  static get styles() {
    return css`
      :host {
        display: inline-block;
      }

      .copy {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.3rem 0.7rem;
        border: 1px solid var(--primary-color, #2c5364);
        border-radius: 4px;
        background: none;
        color: var(--primary-color, #2c5364);
        font: inherit;
        font-size: 0.8125rem;
        font-weight: 700;
        line-height: 1.4;
        cursor: pointer;
      }

      .copy:hover,
      .copy:focus-visible {
        background: var(--primary-color, #2c5364);
        color: var(--surface-1, #fff);
      }

      .copy--copied {
        border-color: var(--success-color, #4caf50);
        color: var(--success-color, #4caf50);
      }

      .copy--copied:hover,
      .copy--copied:focus-visible {
        background: var(--success-color, #4caf50);
        color: var(--surface-1, #fff);
      }

      dt-icon {
        font-size: 1rem;
        display: flex;
      }
    `;
  }

  disconnectedCallback() {
    super.disconnectedCallback();
    clearTimeout(this._resetTimer);
  }

  render() {
    const { copied, label, copiedLabel, icon, title } = this;
    const text = copied ? copiedLabel || label : label;

    return html`
      <button
        class="copy ${copied ? "copy--copied" : ""}"
        type="button"
        title="${title || label}"
        aria-label="${title || label}"
        @click=${this.handleClick}
      >
        <dt-icon icon="${copied ? "mdi:check" : icon}"></dt-icon>
        ${text ? html`<span>${text}</span>` : null}
      </button>
    `;
  }

  async handleClick() {
    if (!this.value) {
      return;
    }

    try {
      await this.copy(this.value);
      this.copied = true;
      clearTimeout(this._resetTimer);
      this._resetTimer = setTimeout(() => {
        this.copied = false;
      }, 2000);
    } catch (error) {
      console.error(error);
    }
  }

  /**
   * navigator.clipboard needs a secure context, and these pages are opened from
   * links people were sent - which can mean plain http on a local network. Fall
   * back to the old selection trick rather than failing silently.
   *
   * @param {string} text
   */
  async copy(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }

    const field = document.createElement("textarea");
    field.value = text;
    field.setAttribute("readonly", "");
    field.style.position = "absolute";
    field.style.left = "-9999px";
    document.body.appendChild(field);
    field.select();

    try {
      document.execCommand("copy");
    } finally {
      document.body.removeChild(field);
    }
  }
}

window.customElements.define("app-copy-link", AppCopyLink);
