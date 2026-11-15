# Shared Tools Staff permissions

Staff Access (`/dashboard/access/`) now includes **Role Permissions**, a single set of checkboxes for every **Tools Staff** account. **Tools Admin** remains unrestricted within Tools and is the only role allowed to edit these checkboxes. WordPress Site Access is a separate setting.

## Initial permissions

| Tools Staff permission | Initial value | Areas |
| --- | --- | --- |
| Weekly Updates & Featured Announcements | Enabled | Weekly Update, app Featured Announcement |
| Messages & Media | Enabled | Message archive, sermon notes/series, media management, Live Slides |
| Calendar & Volunteers | Enabled | Calendar Manager, volunteer needs |
| Prayers & Communications | Enabled | Prayer Requests, app push notifications |
| Homepage & App Appearance | Enabled | Homepage images, app Home |
| Ministries | Enabled | Ministry editor and publishing |
| Church Information & Navigation | Enabled | Surfside Information and website navigation |

An existing saved permission selection overrides these defaults. All Tools Staff members receive the same settings; no per-person exceptions.

**Always admin-only:** Staff Access/role changes, integration settings/API credentials, WordPress configuration, contact routing, saved places, calendar defaults, and streaming destinations. Permission changes do **not** grant a WordPress administrator role or alter WordPress Site Access.

## Security implementation

- A single server-side permission source gates navigation, direct dashboard routes, and designated shortcodes.
- The Ministries and Church Information editors check the permission before processing their forms.
- The streaming section additionally checks Tools Admin for both display and save.
- The dashboard feature gates are separate from WordPress's global `manage_options` capability. Enabling an editor does not confer general settings access.
- Permission options are written only from the Tools Admin Staff Access page after nonce validation.

## Manual verification before merging

1. Sign in as Tools Admin. Open Staff Access, verify the permission explanations, uncheck **Ministries**, save, and reload: the checkbox stays off.
2. Sign in as Tools Staff. Verify Ministries is missing from workspace navigation and a direct visit to `/dashboard/site-ministries/` returns 403.
3. Re-enable Ministries as Admin; as Staff verify you can view and save a ministry.
4. Disable **Church Information & Navigation**; as Staff confirm direct navigation and information links are denied. Re-enable it and confirm edits save.
5. As Staff try `/dashboard/settings/`, `/dashboard/access/`, and `/dashboard/site-streaming/`; integration, staff authorization and streaming configuration must remain restricted.
6. Disable and re-enable each existing feature group; check sidebar links, direct URLs, and typical saves for that group.
7. Verify a Tools Admin is unaffected by Staff role toggles and a user assigned Disabled cannot gain access.
8. Verify WordPress Site Access selection remains unchanged after any Tools Staff permission edit.

> Note: This PR gates the known Surfside Tools dashboard feature entry points and editor forms. Existing WordPress capability checks still apply inside other modules. Do not assume these checkboxes confer arbitrary WordPress privileges.
