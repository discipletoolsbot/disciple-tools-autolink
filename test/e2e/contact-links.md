# E2E: magic link access and contact links

Run against a LocalWP site with this branch deployed, Autolink active and the
Disciple.Tools theme. These are real HTTP requests (browser or curl), not
in-process REST calls - the theme's REST access filter and cookie auth only
apply on a real request.

## Fixtures

- **User link:** the admin user's `autolink/app/<key>`
  (user meta `{prefix}autolink_app_magic_key`).
- **Contact A:** a contact with no login that the admin can edit. Issue it a
  link first (Copy link in the admin's Contacts tab).
- **Contact B:** a contact with `corresponds_to_user` set that holds an
  `autolink_contact_magic_key` (e.g. one minted before links were refused for
  logged in contacts).
- Snapshot `autolink_contact_magic_key` and `autolink_contact_magic_key_expiration`
  on every contact you touch and restore them afterwards.

REST calls send `parts` = `{root: autolink, type, meta_key: autolink_<type>_magic_key, public_key, post_id}`.
The `X-WP-Nonce` comes from the page's `window.app.nonce`.

## Flows

| # | Flow | Expected |
|---|------|----------|
| 1 | Open the user link while logged out | 302 to `/autolink` (sign in) |
| 2 | POST `autolink/v1/app` `action=tree` logged out, with the user link's parts | 401 `rest_forbidden` |
| 3 | Same as 2, logged in as that user, with nonce | 200 |
| 4 | Same as 2, logged in as a *different* user | 403 |
| 5 | Open Contact A's link logged out, `?action=contacts` | 200; My Link tile says "This link works until ..."; "Secure your access" button in the same tile; no separate upgrade tile |
| 6 | GET `autolink/v1/contact` `action=groups` with Contact A's parts, logged out | 200 |
| 7 | POST `autolink/v1/contact` `action=contact_link`, `contact_id` = Contact A's own id | 403 - a link cannot renew itself |
| 8 | As admin, POST `autolink/v1/app` `action=contact_link` for Contact A | 200 with `link`, `expires`, `expires_label`; same key as before; expiry about one validity from now |
| 9 | Open Contact B's link | 302 to `/autolink` - contacts with a login sign in |
| 10 | GET `dt/v1/autolink/group-tree` with Contact A's parts, logged out | 200 with a tree (empty is fine for a contact with no churches) |
| 11 | Move Contact A's expiry into the past (`links_expire_within_base_ts` 8 days back), open the link | Theme "Invalid Link ... no longer valid or has expired" page |
| 12 | Same as 6 with the expired link | 401 |
| 13 | Admin Contacts tab, card of a contact whose link is expired | "Link expired. Copy it to send a new one." beside Copy link |
| 14 | Press Copy link on that card | Clipboard holds a new `autolink/contact/<key>`; status reads "Link works until ..." and stays after the button resets |
| 15 | Contact record in DT, Apps tile | One Autolink row: Contact App (no login) or Share Link (has login), with distinct titles |
| 16 | Autolink admin settings, save without a nonce | Nothing saved |
