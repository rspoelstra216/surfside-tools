# Phase 3C — Member Care and Contact Routing Staff UI

This is intentionally **one subsystem-sized PR** for three related staff workflows,
rather than separate changes to each card or screen. It follows PR #468.

## Scope

### 1. Member Engagement overview
- Show a clear summary of the number of prayer requests awaiting review,
  active requests on the public prayer list, and currently published
  volunteer needs.
- Update the card language to **Prayers and Praises**, consistent with the
  app, while preserving the Prayer Requests routes and existing email links.

### 2. Prayers and Praises review
- Improve the Pending / Active / History tabs with visible counts and
  `aria-current="page"`; show counts in a separate quick summary too.
- Use distinct headers, clear privacy labels, readable request text,
  publication/expiration information and a purposeful empty state.
- Improve button spacing and mobile tap targets. Preserve the existing
  **Approve & Publish**, **Keep Private**, **Archive**, **Mark Answered**,
  **Remove** and **Extend +7/+14/+30 days** actions.
- Maintain existing anonymous display handling. Never show a submitter's
  name in the prayer-list card when they requested anonymity.

### 3. Volunteer Needs editor
- Distinguish saved **Visible in app**, **Not published**, and **Total saved**
  counts; clarify the count reflects the saved state.
- Show each opportunity's title in its heading, instead of the generic
  repeated `Volunteer Need` title, and clarify the Active checkbox as
  **Show in app** (same field/value/semantics).
- Explain how staff reorder, remove, and save changes; improve control
  contrast, spacing and readability on phones.

### 4. Contact Routing
- Present the five routing categories in a readable, labeled responsive
  form instead of an inline-styled vertical list.
- Clearly call out that **Pastoral Staff Only** submissions require an
  **explicit** Speak to a Pastor recipient. The pastoral address input
  represents the stored explicit value, not a general-address fallback.
- Inform staff that other categories use the ordinary church-email
  fallback where applicable and that only future submissions change.
- **No changes to the actual recipient resolution or privacy enforcement.**

## Deliberately unchanged

- No changes to prayer list data structures, REST/mobile payloads,
  publication/expiration rules or push notification timing.
- No changes to prayer approval action names, POST nonces, staff permissions,
  private/anonymous treatment or persistence.
- No changes to volunteer need keys, saved option, order/reorder JS,
  active filtering, deletion or mobile endpoint.
- No changes to Contact routing POST fields/nonces, private pastoral
  routing safeguards, email delivery or Turnstile secrets.
- No database migrations, public styles, or dashboard URL changes.

A single `assets/css/member-care-workspace.css` covers these related
screens and is loaded **only** on authorized staff Dashboard Member
Engagement and Contact Routing pages. It uses the existing hash-based
asset versioning to avoid stale browser caches.

## Smoke test — after deploying PR

1. On **Dashboard → Member Engagement**, verify overview counts for
   pending prayers, active prayers and currently active volunteer needs.
2. Open **Prayers and Praises**. Confirm tabs show accurate counts and
   status dates. Review a pending test request and verify the actions
   appear, but avoid publishing a real submission for the smoke test.
3. On the Active tab, confirm an anonymous prayer shows
   **Anonymous in prayer list**, without disclosing the person's name.
   Verify existing Mark Answered / Remove / Extend options are available.
4. Confirm History is read-only and that empty sections display
   helpful non-alarming text.
5. Open **Volunteer Needs** and check Active vs Inactive summary.
   Edit a disposable need, change its Show in app checkbox, reorder
   using arrows, add/remove a test need, and verify the Save action.
6. Open **Contact Routing** as an admin. Confirm category recipient
   addresses remain correct and Pastoral-only has its own explicit
   destination. **Do not clear/change any production recipient simply
   to test the layout.**
7. On a mobile phone, verify tabs, prayer action controls, the volunteer
   editor, and recipient fields remain readable without horizontal
   scrolling.
8. Verify the public prayer list and app volunteer listings still
   show the expected entries. Published data should be unchanged by
   this presentation-only deployment.

## Automated regression

`scripts/test-member-care-workspace.php` tests displayed counts,
anonymous naming, prayer actions/nonces, volunteer fields, contact
routing privacy status, permissions, scoping and mobile CSS. The existing
`scripts/test-prayer-member-management.php` and
`scripts/test-contact-privacy-routing.php` remain in CI and continue
to validate the sensitive operational rules.
