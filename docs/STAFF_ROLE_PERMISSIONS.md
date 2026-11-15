# Surfside Tools role matrix (PR after #507)

Tools staff permissions are independent of WordPress site roles. Staff Access at `/dashboard/access/` is restricted to **Tools Admin**.

## Role model

- **Tools Admin:** protected, full Tools permission set. Cannot be edited or deleted from this screen.
- **Tools Staff:** existing shared role; each permission starts with the value previously saved through #507's seven group checkboxes. No migration runs on public requests.
- **Custom Tools roles:** created by a Tools Admin with an individual name, up to 12 custom roles. They begin with zero permissions and can be assigned to WordPress-backed accounts or Google-only identities.
- **Disabled / Pending:** not active roles and cannot access Tools.
- **WordPress Site Access:** the existing separate column and WordPress roles remain unchanged.

All permission decisions are computed from the role's saved matrix (not a person's custom exceptions). Unknown or removed roles fail closed.

## Role permissions by area

The expandable matrix includes individual rows for Weekly Update, Featured Announcement, Archived Sermon Notes, Message Archive/Sermon Series, Weekly Media, Live Slides, Calendar Manager, Volunteer Needs, Ministries, Prayer Requests, Push Notifications, Homepage Photos, App Home Experience, Surfside Information, and Website Navigation.

The matrix also **shows** protected, nondelegable rows for streaming configuration, calendar defaults, saved places, contact routing, design settings, integrations overview/API credentials, Google Maps, YouTube, YouVersion, Giving, Cloudflare Turnstile, and Staff/role management. Only the Tools Admin column is checked for these. Admin-only settings are not turned into global WordPress capabilities.

This is an operations permissions matrix, not an arbitrary WordPress capability editor. Some underlying editors also perform their own capability checks, which must remain in place.

## Enforcement and continuity

- The Tools role model powers menu/search/landing links, per-feature dashboard URLs and their POSTs, and designated shortcodes; the Ministries and Site Information editors check their role permission before saving.
- Feature-level controls subdivide #507's former seven groups without losing their current values. The first matrix save stores the new per-role values.
- Firebase UID authorization accepts assigned custom Tools roles and uses the existing locked staff bridge account; WordPress-credential sessions read the current Tools role. Neither grants `manage_options` to a custom role.
- Custom roles cannot be deleted while assigned to a WordPress person or Google identity. The built-in Admin role is immutable, and demotion of the last WordPress Tools administrator is blocked.
- The role editor handles POST requests only on the admin's Staff Access page with WordPress nonces. It does not run database migrations or page-creation checks during general requests.

## Verification checklist (before deployment)

1. Under Staff Access, confirm Tools Admin is fully checked and locked, including all Admin-only entries.
2. Confirm the Tools Staff column matches the saved #507 permission selections. Change one, save, refresh, and verify persistence.
3. Add **Ministry Leader**, verify it appears as a column with no permissions. Check Ministries and Calendar, save, and assign it to a test person.
4. Test WordPress credentials and Google sign-in (including linked identities) with that role; verify granted tools work and forbidden tools are absent and denied on direct URLs.
5. Test Calendar Defaults, Saved Places, Integrations, Contact Routing and Staff Access with the custom role; all remain Admin-only even if an attacker submits forged form keys.
6. Reassign a person, then confirm deleting the custom role is refused while assigned and accepted after nobody is assigned. Confirm removed roles no longer authorize.
7. Verify disabled/pending accounts remain unable to enter Tools and at least one Tools Admin remains.
8. Confirm WordPress Site Access roles have not changed, and existing public app/website behavior is unaffected.

CI runs `scripts/test-staff-role-matrix.php`, covering legacy permissions, custom role creation/removal, privilege isolation, direct-route checks and default denial.
