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
- For flows 17-24, Contact A also needs to lead at least one church
  (**Church A**), coach at least one person, and share one church with a person
  they coach (both listed as its leaders). **Outside contact** and **outside
  church** are a contact and a church that have nothing to do with Contact A: not
  in their coaching downline, not in their churches, and not in their coaches'
  churches.
- Snapshot `autolink_contact_magic_key` and `autolink_contact_magic_key_expiration`
  on every contact you touch and restore them afterwards. Flows 14 and 24 mint
  new keys. Give everything you create a `ZZ E2E` prefix, and delete it
  afterwards along with its `p2p`, `dt_share` and `dt_activity_log` rows and any
  user flow 24 creates.

REST calls send `parts` = `{root: autolink, type, meta_key: autolink_<type>_magic_key, public_key, post_id}`.
The `X-WP-Nonce` comes from the page's `window.app.nonce`. GET calls also need the
page's nonce as `_wpnonce`, the way `churches.js` sends it. Without it a
logged-out GET comes back 401.

## Flows

| # | Flow | Expected |
|---|------|----------|
| 1 | Open the user link while logged out | 302 to `/autolink` (sign in) |
| 2 | POST `autolink/v1/app` `action=tree` logged out, with the user link's parts | 401 `rest_forbidden` |
| 3 | Same as 2, logged in as that user, with nonce | 200 |
| 4 | Same as 2, logged in as a *different* user | 403 |
| 5 | Open Contact A's link logged out, `?action=contacts` | 200; My Link tile says "This link works until ..."; "Secure your access" button in the same tile; no separate upgrade tile |
| 6 | GET `autolink/v1/contact` `action=groups` with Contact A's parts and the page's `_wpnonce`, logged out | 200 |
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

### Church form scoping

The church form saves with permission checks off, so the controller has to limit
what a submission can attach. Post to the form's own `action` URL with the
`_wpnonce` from `?action=create-group`. Leaders go as `leaders[<n>][id]`.

| # | Flow | Expected |
|---|------|----------|
| 17 | On Contact A's link, submit create-group with leader = Contact A and `parent_group` = the outside church | Form re-renders with "Unauthorized action"; no church created |
| 18 | Same, with leaders = Contact A + the outside contact and no parent | Redirects home; the new church's only leader is Contact A |
| 19 | Fill in a name in the real form UI, keep the parent it offers, press Save | Church created, leader = Contact A, parent = the offered church |

### Tree and GenMap

| # | Flow | Expected |
|---|------|----------|
| 20 | On Contact A's link, `?action=tree`, find the church shared with a coachee in `<app-groups-tree>`'s `tree` | In the main tree, not `unassignedTree`; `assigned: true`, `coaching: false` (so it can be dragged) |
| 21 | On Contact A's link, `?action=genmap`, click "+" on Church A | One POST `autolink/v1/contact` `action=genmap_create_node` → 200; a "New" node is drawn under Church A; no reload, alert or stuck spinner. The new church has Contact A as leader and is assigned to the base user |
| 22 | Same as 21 on the admin's user link | POST `autolink/v1/app` → 200; node drawn; no reload |
| 23 | POST `autolink/v1/contact` `genmap_create_node` with `parent_id` = the outside church, and `genmap_update_node` with `group_id` = the outside church | 403 for both; outside church unchanged. `genmap_update_node` on Church A with only an invalid `group_status` → 400 "Nothing to save" |

| 25 | On Contact A's link, click the node from flow 21 to open its dialog, change the name, untick active, press Save Changes | One POST `genmap_update_node` → 200; the dialog closes and the node shows the new name; the church is saved `inactive`; no reload or alert |

### Upgrade keeps the people you coach

| # | Flow | Expected |
|---|------|----------|
| 24 | On Contact A's link add `ZZ E2E Coachee`, then copy their link through `contact_link`. On the coachee's link add `ZZ E2E Grandchild`, create a church, then complete "Secure your access" | Lands in the new user's `autolink/app/<key>` signed in. In the Contacts tab the grandchild has `can_edit` and `can_link` true, and `contact_link` for them → 200. The grandchild and the church are assigned to the new user. The coachee's old contact link shows the theme's "Invalid Link" page |
