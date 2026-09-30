## What changed

What this does and why, in a couple of sentences.

Fixes #

## How it was tested

What you clicked through, which MCP tools you called, and anything you could not test.

## Screenshots

Required for public-site and panel changes. A short clip works too. Delete this section otherwise.

## Needs

- [ ] A migration under `admin/database/migrations`
- [ ] A new `.env` key
- [ ] A container restart to take effect

## Checklist

- [ ] One concern, branched off `master`.
- [ ] Matches the surrounding code — same naming, same idiom, no inline comments.
- [ ] Content field changes update both the HTTP controller and `ContentWriteService`.
- [ ] Schema changes add a new migration and were checked against `main/src/lib/server`.
- [ ] `cd main && npm run build:format && npm run build:sync && npm run build && npx svelte-check`
- [ ] `cd admin && vendor/bin/pint && composer test`
- [ ] Ran the app and clicked through the parts I changed.
