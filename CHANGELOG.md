# Changelog

## [1.2.1] - 2026-07-13

### Clarify event locations ([#2](https://github.com/rspoelstra216/surfside-tools/pull/2))

- Improved the event-location instructions and Google Places wording.
- Renamed the saved location fields to **Venue** and **Street Address**.
- Added clearer search guidance for churches, businesses, and addresses.

### Add automated plugin builds ([#3](https://github.com/rspoelstra216/surfside-tools/pull/3))

- Added a GitHub Actions workflow that validates the plugin and creates an installable WordPress ZIP.
- Established a repeatable build process for merged changes.

### Flatten the repository structure ([#4](https://github.com/rspoelstra216/surfside-tools/pull/4))

- Made the repository root match the WordPress plugin root.
- Removed the extra nested `surfside-tools` directory from generated packages.

### Add cPanel deployment ([#5](https://github.com/rspoelstra216/surfside-tools/pull/5))

- Added cPanel deployment configuration for the live WordPress plugin directory.
- Enabled the GitHub-to-cPanel update and deploy workflow without manual ZIP uploads.

### Add a separate meeting-location field ([#6](https://github.com/rspoelstra216/surfside-tools/pull/6))

- Added an optional **Meeting Location** field for rooms, buildings, and on-campus locations.
- Kept venue and street address separate from details such as Building 4 or Fellowship Hall.

### Display meeting locations publicly ([#7](https://github.com/rspoelstra216/surfside-tools/pull/7))

- Added meeting-location details to public calendar displays and event information.
- Preserved venue, address, and Google Maps details in the event modal.

### Equalize month-calendar week heights ([#8](https://github.com/rspoelstra216/surfside-tools/pull/8))

- Standardized desktop month-view week heights.
- Added overflow handling so busy dates do not stretch the entire calendar row.

### Polish month-view event cards ([#9](https://github.com/rspoelstra216/surfside-tools/pull/9))

- Compacted calendar cards so more information fits without changing row height.
- Improved spacing and typography in the month view.
- Added the `+N more` indicator when additional events are hidden.

### Automate official releases ([#10](https://github.com/rspoelstra216/surfside-tools/pull/10))

- Added automatic version updates, PHP validation, changelog generation, Git tags, GitHub Releases, and installable ZIP creation.
- Kept cPanel Git as the live deployment method while adding a permanent release archive.

Release entries are generated automatically by the **Release Surfside Tools** GitHub Actions workflow.

The workflow places the newest release at the top of this file and builds the release notes from pull requests merged since the previous Git tag.
