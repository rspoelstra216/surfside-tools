# Weekly Media Publishing — podcast metadata preview

The staff navigation label is **Weekly Media**, with **Weekly Media Publishing** as the page title. The existing \`view=roku-media\` route stays intact for existing links and upload authorization.

The workspace retains the YouTube lookup and direct-to-R2 MP4 upload and adds a **read-only Spreaker metadata preview** for already verified R2 sermons. After a successful weekly upload, the selected message appears in the podcast preview without a page reload.

## Proposed naming convention

A structured YouTube title such as \`Message - Series - Week N - 26 July 2026\` produces \`Message | Series (Week N)\`. Additional series parts are joined using a colon, for example \`Christ Above All: Colossians\`. The description includes the sermon date from the existing metadata, the YouTube URL and Surfside website URL. YouTube publishing dates are not substituted for sermon dates.

Unrecognized titles, missing sermon dates, invalid IDs and titles beyond the documented 140-character Spreaker limit are flagged for review rather than guessed.

## Boundary

**This first PR does not generate audio, configure Cloudflare Containers, call Spreaker, create drafts or publish podcasts.** It prepares the consolidated weekly page and lets staff validate the proposed naming convention without disrupting the live Roku uploader.

The next implementation can add FFmpeg/Container processing and a Spreaker draft workflow, with retries and idempotency by YouTube ID, triggered only by verified *new weekly* uploads. Historical imported MP4s are excluded.
