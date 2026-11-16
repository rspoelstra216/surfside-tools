# Surfside Tools permission matrix

Staff Access (`/dashboard/access/`) manages Tools roles independently of WordPress site roles.

## Role permissions

- **Tools Admin**: immutable full access, including Staff Access and role management.
- **Tools Staff**: the existing shared role with persisted permissions from PRs #507 and #508.
- **Custom roles**: created and assigned through Staff Access. Each starts without any permissions.
- **Disabled/Pending**: no Tools authorization.

The matrix contains all editable feature permissions, including advanced settings previously shown as "Admin only": Streaming Configuration, Calendar Defaults, Saved Places, Contact Routing, Reveal & Countdown Styling, All Integrations, Google Maps, YouTube, YouVersion, Giving, and Cloudflare Turnstile. **All are selectable for Staff or custom roles.** They default **off** for non-admin roles on upgrade.

**Staff Access & Role Management** is the one permanently protected capability. It can never be enabled for non-admin roles through the matrix; this prevents a role from granting itself access.

"All Integrations" is a convenience permission that grants each integration's edit capability. Individual integration permissions can also be selected independently. A role with just Google Maps can access only that part of Integrations. The shared Giving/Turnstile form checks both permissions independently and cannot overwrite the settings of a service the role cannot edit.

No Tools role checkbox grants WordPress's global `manage_options` permission; WordPress Site Access is separately managed. All changes use server-side checks, not merely hidden menu items, including known direct dashboard routes and corresponding POST handlers. Existing Staff selections are preserved.

## Manual verification before production

1. In Staff Access, verify rows previously labeled "Admin only" are now interactive checkboxes for Staff and custom roles; Staff Access & Role Management remains locked.
2. Enable **Calendar Defaults** for a test role with Calendar Manager disabled. Verify the Defaults screen works, Calendar events cannot be edited, and direct access to Saved Places is denied.
3. Grant **Saved Places** separately and verify removal/restore operations and nonce checks.
4. Grant **Contact Routing** independently and confirm recipient addresses can be updated, while Staff Access remains unavailable.
5. Grant **Streaming Configuration** to a non-admin test role and verify Cloudflare recording settings, Watch Live settings, offline announcements, and Twitch controls without granting WordPress administration.
6. Grant **Google Maps** only. Verify Maps settings render and save; YouTube, YouVersion, Giving, Turnstile, and visual CSS are not accessible.
7. Grant **Giving** only, then **Turnstile** only. Save each and verify the other service's saved values are unchanged. Test a forged POST with unauthorized fields.
8. Test YouTube and YouVersion independently with WordPress and Google Tools login. Verify direct POST actions and sensitive credentials remain inaccessible without the corresponding permission.
9. Disable advanced permissions and confirm navigation, direct routes, and POSTs are denied.
10. Confirm Tools Admin retains access and existing Tools Staff and custom-role assignments stay unchanged.

CI includes `scripts/test-staff-role-matrix.php` alongside the established WordPress plugin validation scripts.
