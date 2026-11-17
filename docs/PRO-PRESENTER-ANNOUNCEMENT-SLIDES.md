# Weekly Announcements to ProPresenter slide preparation

## Workflow

The secretary continues using **Staff Dashboard → Weekly Update → Upload & Review**.
When the announcements DOCX parses successfully, Surfside Tools immediately calls
OpenAI to create a staff-only ProPresenter summary for every announcement.

The ProPresenter preparation card on /dashboard/weekly-update/ displays the
result, **before** the document is scheduled for Saturday. Staff can:

- Review and edit each slide title and body (one to three body lines).
- **Save edits** without changing the underlying church announcements.
- **Copy slide** or **Copy all** (plain text, separated by --- lines).
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

## Server-side API key

An administrator must configure an OpenAI API key in the server's
wp-config.php, before the WordPress stop-editing line:

    define('SURFSIDE_TOOLS_OPENAI_API_KEY', 'YOUR_SECRET_OPENAI_API_KEY');

For safer secret management, use a server environment variable if available:

    define('SURFSIDE_TOOLS_OPENAI_API_KEY', getenv('SURFSIDE_TOOLS_OPENAI_API_KEY') ?: '');

Never commit a live key to GitHub or place it in public HTML, an app bundle,
WordPress page content, or JavaScript. OpenAI API use is billed separately
from ChatGPT. The default model is gpt-5.4-mini.

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

1. Add the API key and confirm sufficient API credits.
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
