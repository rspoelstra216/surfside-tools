# Surfside Tools Roadmap

Development is organized around milestones rather than isolated version numbers. Version tags remain useful for releases, but the milestone is the primary measure of progress.

## Milestone 1.2.x — Calendar

### Completed

- Event manager
- Recurrence
- Month view
- Weekly view
- Google Places connection
- Venue and street-address support
- Separate meeting-location field
- cPanel Git deployment workflow

### In progress

- Calendar polish
- Equal-height month-grid weeks
- Limit visible events per day with `+N more`
- Public location-display consistency
- Final Google Places refinements

### Release infrastructure

- Automatic version bumping
- Automatic release ZIP creation
- Automatic GitHub Releases
- Changelog generation

The cPanel deployment workflow remains the primary deployment method. Release ZIPs will provide a clean downloadable fallback and a permanent release archive.

## Milestone 1.3 — Productivity

- Announcement-to-calendar suggestions
- One-click event creation
- Better event management
- Search and filter improvements

## Milestone 1.4 — Church Portal

- Homepage widgets
- Dashboard improvements
- Additional integrations

## Working process

1. Build changes on a feature branch.
2. Review and merge a pull request.
3. Update from Remote in cPanel.
4. Deploy the HEAD commit.
5. Verify the change on the live site.
6. Group completed work into milestone releases with generated notes and downloadable assets.
