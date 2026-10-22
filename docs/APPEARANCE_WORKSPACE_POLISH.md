# Phase 3E — Appearance Management: Homepage Photos, Website Navigation and App Home

## Purpose

A single moderately scoped PR to finish the remaining high-use appearance
editors on Surfside Tools. It follows merged Phase 3D (#470) and does not
modify public rendering, uploaded media contents, mobile API fields, or
WordPress site information option schemas.

## 1. Homepage Photos

- Display the current number of homepage carousel photos and remaining
  slots (maximum 30) above the gallery.
- Keep **desktop drag-to-reorder** and provide **Move Up / Move Down**
  buttons on every card for phones, keyboard users, and easier precise
  control. Buttons update the existing DOM order and preserve
  `image_order[]` hidden fields; there is no new backend ordering path.
- Disabled controls at first/last position prevent invalid moves.
  When rows move, photo numbers and accessible button labels update.
- Explain that additions, reordering, replacements and removals only
  take effect when **Save Homepage Photos** is pressed.
- Maintain upload/replacement/remove fields, original nonce, limits,
  Media Library behavior, and sticky Save bar.

## 2. Website Navigation

- Keep the existing header/footer navigation editor, including published
  page options and custom URL destinations.
- Show the number of links being edited and numbered link positions.
  Counts and positions update as links are added, moved, or removed.
- Improve the arrangement of labels, selection controls and arrow/remove
  actions, especially on phones.
- Make clear that changes do not publish until **Save Navigation**.
- Keep the admin-only capability, saved keys, page-vs-custom links,
  drag-and-drop, Up/Down reordering and section-specific POST handler.

## 3. App Home Experience

- Clearly separate the **saved** image choice and crop/zoom numbers
  from the **editor preview** that can show unsaved changes.
- Explain that preview text/cards are an illustration of content
  placement, not current live event data.
- Clarify that **Save Home Experience** applies image/crop changes
  to the mobile app, while removing the image restores the default
  branded hero.
- Improve zoom, crop, image-selection actions and mobile save layout.
- Keep WP Media Library selection, drag-to-crop, reset, focal X/Y, zoom
  (100–200%), update nonce, saved setting names, and existing app payload.

## Shared appearance styles

- `assets/css/appearance-workspace.css` targets the three staff screens,
  with consistent counters, guidance, buttons, focus rings, and responsive
  layouts at tablet/mobile widths.
- Loaded by the existing staff navigation asset hook for:
  `/dashboard/homepage/`, `/dashboard/site-navigation/`, and
  `/dashboard/mobile-app/home-experience/`.
- Only loaded for authorized staff dashboard requests, never on the
  public WordPress site, mobile app or other dashboard screens.
- File-content versioning avoids old browser CSS without bumping plugin
  version.

## Verification

- `scripts/test-appearance-workspace.php` renders the actual staff
  templates against test data; confirms photo count, move buttons,
  existing save fields/nonces, navigation order and page/custom link
  POST behavior, app Home zoom/crop POST behavior, capability gates
  and route-specific CSS loading.
- Existing site information, app API and homepage archive workflows
  are untouched; all current GitHub validation steps remain enabled.

## Manual test plan — before merge

1. **Homepage Photos (phone and desktop):** Open with existing
   carousel photos; verify count and available slots. Use Move Down
   and Move Up on a disposable photo sequence, then save. Reload to
   confirm persisted order. On desktop, also check drag still works.
   Confirm removed images remain in the Media Library.
2. **Website Navigation (admin only):** Confirm the displayed link
   count. Move a link with arrow controls and verify order numbers
   update, then save/reload. Try choosing a published page and
   opening a Custom URL link, without accidentally removing
   production navigation links.
3. **App Home Experience:** Confirm the saved hero status and
   zoom/position summary match current settings. Move the preview
   image, zoom and click Center & Reset. Confirm the UI doesn't
   imply edits are live until saved. If changing production hero,
   only save an intended image.
4. At phone width (~375–400px), verify all three screens remain
   readable and usable, without horizontal scroll or hidden Save.
5. Confirm public website header/footer, homepage photo carousel,
   and mobile Home hero remain as expected after deployment.
   Changes to publication/data aren't part of this PR.

## Deferred from earlier work

The written sermon-note archive may have no real entries until the
scheduled publishing cycle first replaces an older document. Its
real-data smoke test remains pending, independent of this PR.

## Explicitly out of scope

No change to media storage, uploads/validation, public carousel JS,
homepage caching, navigation routing, branded mobile hero rendering,
notification rules, sermon archival, Roku/R2 or scheduled publishing.
