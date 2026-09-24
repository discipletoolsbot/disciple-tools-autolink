import { html, nothing } from "lit";
import { DtBase } from "@disciple.tools/web-components";

/**
 * The row of health count badges shown above a church's health circle.
 *
 * Ported from the abandoned 2.0 branch (`resources/js/components/_church-count.js`).
 * Master used to render these inline in `app-churches` and repaint the badge from a
 * document level `change` listener; holding the edited values here instead lets Lit
 * repaint the badge as part of a normal render.
 *
 * The editor is a plain `<dialog>` rather than `dt-modal`. dt-modal is built for
 * form tiles - it always renders a titled header and a bordered footer, which is a
 * lot of chrome around a single number input, and none of it is reachable from
 * outside its shadow root. A native dialog gives us the backdrop, Escape handling
 * and focus trapping for free and leaves the styling to `_churches.css`.
 */
export class AppChurchCounts extends DtBase {
  static get properties() {
    return {
      ...super.properties,
      group: { type: Object },
      countFields: { type: Object },
      counts: { type: Object, state: true },
    };
  }

  constructor() {
    super();
    this.group = {};
    this.countFields = {};
    this.counts = {};
  }

  createRenderRoot() {
    return this; // light DOM, so magic-link.css applies
  }

  get closeLabel() {
    return window.app?.translations?.close || "Close";
  }

  /**
   * The value to show on a badge: whatever the leader has edited this page
   * load, otherwise whatever the group arrived with.
   *
   * @param {string} key
   *
   * @returns {string|number}
   */
  countFor(key) {
    return this.counts[key] ?? this.group[key] ?? 0;
  }

  /**
   * @param {string} key
   * @param {string|number} value
   */
  setCount(key, value) {
    this.counts = {
      ...this.counts,
      [key]: value === "" || value === undefined || value === null ? 0 : value,
    };
  }

  handleFieldInput(e) {
    this.setCount(e.target.name, e.target.value);
  }

  handleFieldChange(e) {
    this.setCount(e.target.name, e.detail?.newValue ?? e.target.value);
  }

  dialogFor(el) {
    return el.closest(".church__count")?.querySelector("dialog");
  }

  handleCountClick(e) {
    this.dialogFor(e.currentTarget)?.showModal();
  }

  handleCloseClick(e) {
    this.dialogFor(e.currentTarget)?.close();
  }

  /**
   * A click that lands on the dialog element itself rather than its contents is
   * a click on the backdrop, which should dismiss it.
   */
  handleDialogClick(e) {
    if (e.target === e.currentTarget) {
      e.currentTarget.close();
    }
  }

  render() {
    const { countFields } = this;

    if (!countFields || !Object.keys(countFields).length) {
      return nothing;
    }

    return html`
      <div class="church__counts">
        ${Object.entries(countFields).map(([key, field]) =>
          this.renderCount(key, field)
        )}
      </div>
    `;
  }

  renderCount(key, field) {
    const { group } = this;
    const value = this.countFor(key);

    return html`
      <div
        class="church__count"
        data-churchId="${group.ID}"
        data-field="${key}"
        key="church-${group.ID}-${key}"
      >
        <button
          type="button"
          class="count__button"
          aria-label="${field.name}"
          @click="${this.handleCountClick}"
        >
          <img
            class="count__icon"
            src="${field.icon}"
            alt=""
            width="25"
            height="25"
          />
          <span class="count__value">${value}</span>
        </button>

        <dialog class="count__dialog" @click="${this.handleDialogClick}">
          <app-church-health-field
            id="groups_${group.ID}_${key}"
            name="${key}"
            icon="${field.icon}"
            label="${field.name}"
            value="${value}"
            postType="groups"
            postId="${group.ID}"
            min="0"
            placeholder="0"
            nonce="${window.app.nonce}"
            @input="${this.handleFieldInput}"
            @change="${this.handleFieldChange}"
          ></app-church-health-field>

          <dt-button
            class="count__dialog-close"
            context="primary"
            @click="${this.handleCloseClick}"
          >
            ${this.closeLabel}
          </dt-button>
        </dialog>
      </div>
    `;
  }
}

window.customElements.define("app-church-counts", AppChurchCounts);
