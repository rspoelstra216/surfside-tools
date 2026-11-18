# Weekly Announcements to ProPresenter slide preparation

## Workflow

The secretary continues using **Staff Dashboard → Weekly Update → Upload & Review**.
When the announcements DOCX parses successfully, Surfside Tools immediately calls
OpenAI to create a staff-only ProPresenter summary for every announcement.

The compact **Announcement slides** section appears at the **bottom** of
/dashboard/weekly-update/, beneath Upload & Review and the Schedule button.
It starts collapsed to keep the regular weekly workflow short. Its header
shows the slide count or whether attention is needed; staff can expand it to
review slides **immediately after upload**, before scheduling for Saturday.
Staff can:

- Review and edit each slide title and body (one to three body lines).
- **Save edits** without changing the underlying church announcements.
- **Copy slide** to copy one announcement or **Copy all** to copy the
  entire week's announcement text as plain text, with one newline between
  lines and no extra blank lines or separator markers.
- **Regenerate AI slides** if a result needs a new pass. This discards edits.
- Optionally email new summaries automatically to configured recipients, or
  use **Email slides** manually.

Staff must still review dates, names, times, locations and other critical
details. This is slide-preparation assistance, not a public publishing step.

### When edits happen to the announcements

If the secretary updates an announcement while reviewing and then schedules
it, Tools checks whether the approved source changed. If it did, the summaries
are regenerated from the approved version. If it did not, the prior slides
(and any manual slide edits) are preserved without another API call.

The summarized source and slides are stored privately in WordPress options,
independent of the published announcements and sermon notes. A newer
announcement upload supersedes the previous slide draft. DOCX files continue
to be deleted after parsing, per the existing importer.

### Saturday release remains unchanged

Nothing in the new feature modifies the Saturday 5 PM Eastern release, sermon
archive, backup rotation, mobile API or public announcements display. A
missing API key, timeout, rate limit, malformed response, or failed email is
reported in the staff UI without blocking Upload & Review or Schedule.

## Copy All for ProPresenter Reflow

Copy All exports all generated announcements in order, using plain text.
Each announcement retains its title and body lines; the next announcement
starts on the next line with **no blank row or divider** between items.

1. Expand **Announcement slides** at the bottom of Weekly Update.
2. Review and optionally edit the announcement text; click **Save edits**
   if those changes should persist.
3. Click **Copy all** and paste the text into ProPresenter's Reflow Editor.
4. Add slide breaks between announcements manually, then apply the existing
   slide Theme and adjust title emphasis as needed.

This is an intentionally minimal text-only workflow, not an automated import:
Copy All does not add slide breaks, change the existing ProPresenter
presentation, or preserve title/body font styling. Copy slide remains
available for pasting one announcement at a time.

## OpenAI API key setup

Recommended: Go to **Staff Dashboard → Church Settings → Integrations →
OpenAI — ProPresenter Announcements**, paste the API key, and click
**Save OpenAI Key**. Then select **Test OpenAI Connection**.

The password field is always blank on page load; a saved key is indicated
only by status text. Leaving the input blank preserves the existing value.
Administrators can explicitly remove a saved key. WordPress users without
the native `manage_options` permission cannot view, save, test, or remove
credentials.

The key is stored as a non-autoloaded WordPress option and is retrieved only
on the PHP server to authenticate OpenAI calls. Unlike wp-config.php, WordPress
database storage requires protecting database backups and administrator
access. The key is never rendered back into the dashboard or public pages.

**Alternative:** An administrator can explicitly set the key in wp-config.php
before the WordPress stop-editing line:

    define('SURFSIDE_TOOLS_OPENAI_API_KEY', 'YOUR_SECRET_OPENAI_API_KEY');

Or with a server environment variable:

    define('SURFSIDE_TOOLS_OPENAI_API_KEY', getenv('SURFSIDE_TOOLS_OPENAI_API_KEY') ?: '');

A nonempty server constant overrides the dashboard-stored key and disables
saving or removing it through the dashboard. An empty constant allows the
dashboard key to be used.

Never commit a live key to GitHub or place it in public HTML, an app bundle,
WordPress page content, or JavaScript. OpenAI API use is billed separately
from ChatGPT. The default model is gpt-5.4-mini. The connection test checks
authorization and access to that model; it does **not** verify remaining
billing credits or generate a sample summary.

The request uses the Chat Completions API with strict structured JSON output,
store false, a bounded output budget and no web lookup. The model receives the
parsed announcement items and their date, not the DOCX attachment or unrelated
church/user data.

## Optional email delivery

In the staff ProPresenter card, WordPress administrators can expand
**Automatic slide email (optional)** to set up to 10 comma/semicolon/newline
separated recipient addresses and enable automatic email.

When enabled, email is sent immediately after a *new successful upload*.
Re-uploading identical announcements does not send another message.
Regeneration or scheduling does not automatically email. A manual **Email
slides** button saves current edits and sends to the configured recipients.
Email uses WordPress wp_mail; the hosting mail configuration must function.

## Testing

The CI test scripts/test-propresenter-announcements.php uses a fake API and
fake mail implementation to verify immediate generation, safe failure when
unconfigured, structured-output constraints, copyable email text, duplicate
upload reuse, changed-source regeneration, and staff-only rendering.

Manual production smoke test:

1. Add the API key in Church Settings → Integrations → OpenAI, test access, and confirm sufficient API credits.
2. Upload sample announcements DOCX using Upload & Review. Verify slides
   appear immediately, **before** clicking Schedule.
3. Compare each summary with the original dates, times, and locations.
4. Edit a slide and Save; reload and confirm the changes persist. Try Copy All
   and Copy Slide in a browser that supports clipboard access.
5. Schedule the unchanged source: confirm slide edits remain. Upload revised
   announcements and confirm changed source regenerates.
6. Opt in an administrator-controlled test email address, upload another
   revised document, confirm exactly one email is delivered, and manually
   test Email slides.
7. Temporarily remove the API key or use an invalid one to verify the DOCX
   can still be reviewed and scheduled and a visible error appears.
8. Verify public website and app announcements remain on the old version
   until Saturday's scheduled release.
