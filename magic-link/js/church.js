import { css, html, nothing } from "lit";
import { AppCollapse } from "./collapse";

export class AppChurch extends AppCollapse {
  static get properties() {
    return {
      group: { type: Object },
      fields: { type: Object },
      opened: { type: Boolean, reflect: true },
      startDateLabel: { type: String },
    };
  }

  constructor() {
    super();
  }

  static get styles() {
    return css`
      :host {
        color: currentcolor;
        display: block;
      }

      .church_health {
        text-align: center;
      }

      .collapse__icon {
        font-size: 2rem;
        display: flex;
        justify-content: center;
        padding-top: 1rem;
      }

      .group__content {
        font-weight: normal;
        text-align: center;
        text-transform: uppercase;
      }
    `;
  }

  render() {
    return html`
      <div class="group">${this.renderContent()} ${this.renderIcon()}</div>
    `;
  }

  renderContent() {
    const { startDateLabel } = this;
    const startDate = this.group.start_date?.formatted;

    if (this.opened) {
      return html`
        ${this.renderChurchHealth()}
        ${startDate
          ? html`<div class="group__content">
              ${startDateLabel ? startDateLabel : "Church start date"} :
              ${this.group.start_date?.formatted}
            </div>`
          : nothing}
      `;
    }
    return nothing;
  }

  /**
   * Persist a health_metrics change through the Autolink magic link endpoint.
   *
   * The endpoint is whitelist gated (see the `autolink_updatable_group_fields`
   * filter), which is what keeps low privilege leaders from writing arbitrary
   * fields, so we save through it rather than straight to the DT posts API.
   *
   * @param {string[]} health_metrics Values from the component. Removals are
   *                                  prefixed with `-`, as dt-multi-select emits them.
   */
  async handleSave(group_id, health_metrics) {
    const params = {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": window.app.nonce,
      },
      body: JSON.stringify({
        id: `groups_${group_id}_health_metrics`,
        value: health_metrics,
        action: "update_field",
        parts: window.magic.parts,
      }),
    };

    const response = await fetch(
      window.app.rest_base + window.magic.rest_namespace,
      params
    );
    const body = await response.json();

    if (body.data && body.data.status && body.data.status !== 200) {
      throw new Error(body.message);
    } else if (body.success == false) {
      throw new Error(body.data.message);
    }

    return body;
  }

  async handleHealthChange(e) {
    const newValue = e.detail?.newValue || [];

    try {
      const body = await this.handleSave(this.group.ID, newValue);

      // Keep our copy of the group in step with what was saved, so a
      // re-render doesn't flip the icons back to their previous state.
      const saved = body?.data?.health_metrics;
      this.group = {
        ...this.group,
        health_metrics: Array.isArray(saved)
          ? saved
          : newValue.filter((metric) => !metric.startsWith("-")),
      };
    } catch (error) {
      console.error(error);
    }
  }

  renderChurchHealth() {
    const options = this.fields?.health_metrics;

    // dt-church-health-circle throws while rendering if it has no options,
    // so don't mount it until the field settings have arrived.
    if (!options || !Object.keys(options).length) {
      return nothing;
    }

    return html`
      <div class="church_health">
        <dt-church-health-circle
          name="health_metrics"
          .options=${options}
          .value=${this.group.health_metrics || []}
          @change=${this.handleHealthChange}
        ></dt-church-health-circle>
      </div>
    `;
  }
}

window.customElements.define("app-church", AppChurch);
