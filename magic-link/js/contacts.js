import { html } from "lit";
import { DtBase } from "@disciple.tools/web-components";

/**
 * Fallbacks for the connection icons.
 *
 * The real ones come from the Disciple.Tools field each connection represents -
 * see Disciple_Tools_Autolink_Contacts_Controller::relationship_icons() - so
 * that an admin who changes a field's icon changes these too. These stand in
 * only when a field carries no icon at all.
 */
const FALLBACK_ICONS = {
  coaching: "mdi:account-supervisor",
  assigned: "mdi:account-check",
  leader: "mdi:crown",
  member: "mdi:church",
};

/**
 * @class Contacts
 *
 * The people around this leader, as cards matching the churches tab.
 *
 * Rendered into the light DOM like <app-churches>, so the app stylesheet reaches
 * the cards and the two tabs stay in step.
 */
export class Contacts extends DtBase {
  static get properties() {
    return {
      ...super.properties,
      contacts: { type: Array },
      translations: { type: Object },
      relationshipIcons: { type: Object },
    };
  }

  constructor() {
    super();
    this.contacts = [];
    this.translations = {};
    this.relationshipIcons = {};
  }

  createRenderRoot() {
    return this;
  }

  render() {
    const { contacts, translations } = this;

    if (!contacts || !contacts.length) {
      return html`<p class="contacts__empty">${translations.empty}</p>`;
    }

    return html`
      <div class="contacts__list">
        ${contacts.map((contact) => this.renderContact(contact))}
      </div>
    `;
  }

  renderContact(contact) {
    return html`
      <contact-tile
        class="contact"
        title="${contact.name}"
        key="contact-${contact.id}"
      >
        ${this.renderLoginMark(contact)} ${this.renderConnections(contact)}
        ${this.renderActions(contact)}
      </contact-tile>
    `;
  }

  /**
   * The one mark that is ours rather than a field's: there is no Disciple.Tools
   * field behind "this person has their own login".
   *
   * Slotted in immediately after the name, so it reads as a fact about this
   * person rather than an action sitting in the corner.
   */
  renderLoginMark(contact) {
    const { translations } = this;

    if (!contact.has_login) {
      return null;
    }

    return html`
      <span
        class="contact__login"
        slot="after-name"
        title="${translations.has_login}"
        aria-label="${translations.has_login}"
      >
        <dt-icon icon="mdi:account-lock"></dt-icon>
      </span>
    `;
  }

  /**
   * How you know this person: a badge per kind, then the churches those came
   * from written out underneath.
   */
  renderConnections(contact) {
    const connections = contact.relationships || [];

    if (!connections.length) {
      return null;
    }

    const details = connections.filter((connection) => connection.detail);

    return html`
      <div class="contact__badges">
        ${connections.map(
          (connection) => html`
            <span
              class="contact__badge contact__badge--${connection.type}"
            >
              ${this.renderConnectionIcon(connection.type)}
              <span>${connection.label}</span>
            </span>
          `
        )}
      </div>
      ${details.length
        ? html`
            <ul class="contact__churches">
              ${details.map(
                (connection) =>
                  html`<li class="contact__church">${connection.detail}</li>`
              )}
            </ul>
          `
        : null}
    `;
  }

  /**
   * A field's own icon where it has one, its font icon where it has that
   * instead, and our fallback where it has neither.
   *
   * @param {string} type
   */
  renderConnectionIcon(type) {
    const field = this.relationshipIcons[type] || {};

    if (field.icon) {
      return html`
        <img
          class="contact__badge-icon"
          src="${field.icon}"
          alt=""
          width="16"
          height="16"
        />
      `;
    }

    return html`
      <dt-icon
        class="contact__badge-icon"
        icon="${field.font_icon || FALLBACK_ICONS[type] || "mdi:account"}"
      ></dt-icon>
    `;
  }

  /**
   * One button, one label.
   *
   * Where the link goes still depends on whether this person has a login - their
   * own magic link if not, the ordinary sign-in address if so, because a magic
   * key into an account with a password would hand over everything that account
   * can reach. That is our distinction to keep, not theirs to read.
   */
  renderActions(contact) {
    const { translations } = this;

    if (!contact.link) {
      return null;
    }

    return html`
      <div class="contact__actions">
        <app-copy-link
          value="${contact.link}"
          label="${translations.copy_link}"
          copiedLabel="${translations.copied}"
        ></app-copy-link>
      </div>
    `;
  }
}

window.customElements.define("app-contacts", Contacts);
