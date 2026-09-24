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
 * The modal trigger is ours rather than the library's: `dt-modal` dropped its
 * `openButton` slot in web components 1.0, so we render the icon and badge
 * ourselves and open the modal by dispatching `open` at it.
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

  handleCountClick(e) {
    const modal = e.currentTarget
      .closest(".church__count")
      ?.querySelector("dt-modal");

    if (modal) {
      modal.dispatchEvent(new CustomEvent("open"));
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
            alt="${field.name}"
            width="25"
            height="25"
          />
          <span class="count__value">${value}</span>
        </button>

        <dt-modal context="default" title="${group.post_title}" closeButton hideButton>
          <div slot="content">
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
          </div>
        </dt-modal>
      </div>
    `;
  }
}

window.customElements.define("app-church-counts", AppChurchCounts);
