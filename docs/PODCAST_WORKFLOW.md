# Weekly Media Publishing — podcast metadata preview

The staff navigation label is **Weekly Media**, with **Weekly Media Publishing** as the page title. The existing \`view=roku-media\` route stays intact for existing links and upload authorization.

The workspace retains the YouTube lookup and direct-to-R2 MP4 upload and adds a **read-only Spreaker metadata preview** for already verified R2 sermons. After a successful weekly upload, the selected message appears in the podcast preview without a page reload.

## Proposed naming convention

A structured YouTube title such as \`Message - Series - Week N - 26 July 2026\` produces \`Message | Series (Week N)\`. Additional series parts are joined using a colon, for example \`Christ Above All: Colossians\`. The description includes the sermon date from the existing metadata, the YouTube URL and Surfside website URL. YouTube publishing dates are not substituted for sermon dates.

Unrecognized titles, missing sermon dates, invalid IDs and titles beyond the documented 140-character Spreaker limit are flagged for review rather than guessed.

## Boundary

**This first PR does not generate audio, configure Cloudflare Containers, call Spreaker, create drafts or publish podcasts.** It prepares the consolidated weekly page and lets staff validate the proposed naming convention without disrupting the live Roku uploader.

The next implementation can add FFmpeg/Container processing and a Spreaker draft workflow, with retries and idempotency by YouTube ID, triggered only by verified *new weekly* uploads. Historical imported MP4s are excluded.


## Embedding archived sermon notes (November 2026)

On the staff **Weekly Media Publishing** page, both the upload dropdown and the
**Podcast episode preview** use the same server-side description builder as the
Spreaker draft/auto-publication job. When a sermon has already been published
through Surfside Tools, its permanent text-only WordPress archive entry supplies
the main heading, written message notes, and closing prayer. The description
shows those notes before the existing YouTube and Surfside links.

Matching is intentionally strict: the canonical YouTube sermon date and the
normalized **message title** must both match one unique archive record.
When Weekly Update records a combined Saturday/Sunday service date such as
`Nov 7/8, 2026`, the matcher recognizes it as the same weekend as canonical
YouTube date `2026-11-08` (the Sunday). It also accepts the equivalent full-month
and common separator variants, including dates crossing month boundaries.
It does not search arbitrary neighboring dates, and title matching is still required.
Publication saves the newly published sermon in the archive immediately, so
the weekly podcast does not need to wait for the next set of notes. For a missing,
uninitialized, empty, or ambiguous archive match, Spreaker still receives the
original description and staff see an explicit notes status. Nothing is guessed
from another sermon on the same date.

Descriptions are capped at 8,000 UTF-8 bytes. Exceptionally long written notes
are visibly shortened with a marker; the source links are preserved. This
change does not create public sermon-note pages or alter the WordPress archive,
R2 MP4/MP3 storage, Spreaker credentials, or Cloudflare audio processing.
Existing Spreaker episodes are not retroactively updated; the enhancement
applies to newly prepared weekly metadata and staff-initiated missing-draft
recovery.

## Podcast show-note readability

The canonical WordPress archive retains the original text, including separate
sermon points, Bible references and Word-importer markup. The podcast
description builder now converts that source into **plain-text paragraphs**,
keeping numbered points, quoted passages, questions, main headings and
closing prayers clearly separated. Internal importer tokens such as
`[[CALLOUT]]` and `[[PRAYER]]` are removed from the rendered description,
without deleting the quotation or prayer text.

The matching status appears *above* the lengthy description in the Weekly
Media Publishing preview. Sermons not present in the permanent WordPress
archive (or without a matching title/date) retain the ordinary description.
This is intentionally not an automatic historical notes backfill. Existing
Spreaker episodes are not modified; for newly prepared episodes, the
automated Cloudflare-to-Spreaker process already uses this same metadata.
