# Contributing

Thanks for taking the time to help. This project is a monorepo — a SvelteKit public site (`main`) and a Laravel admin panel (`admin`) sharing one MySQL database — and contributions of every size are welcome, a typo fix in the panel copy counts.

By taking part you agree to the [Code of Conduct](CODE_OF_CONDUCT.md). Contributions are accepted under the [MIT License](LICENSE).

## Before you start

- **Security issues do not belong in issues or pull requests.** Email **security@dansday.com** — see [SECURITY.md](SECURITY.md).
- Open an issue first for anything that adds a page, changes the database schema, or reshapes an admin section. A short discussion beats a rejected branch.
- Small, obvious fixes (typos, broken links, a wrong label) can go straight to a pull request.

## Local setup

You need Node.js 25 and PHP 8.5 (the versions the Docker images pin), plus MySQL, and Redis for the panel's cache, queues and sessions. Neither is in the Compose stack — point `DB_*` and `REDIS_*` at your own instances.

```bash
git clone https://github.com/dansday-com/dansday-main.git
cd dansday-main
make install                # deps for both apps, and copies .env files
```

Then run each app:

```bash
cd admin && composer setup  # key:generate, migrate, npm build
composer dev                # serve + queue + logs + vite

cd main && npm run dev      # public site on http://localhost:5173
```

Or run the whole stack in containers:

```bash
make up      # build and start
make down    # stop
```

The admin panel seeds itself on first visit when no settings row exists, so a fresh database gives you a working panel. If you hit it through the MCP endpoint first, that middleware does not run — seed manually with `php artisan db:seed`.

## Project layout

| Path                       | What lives there                                                          |
| -------------------------- | ------------------------------------------------------------------------- |
| `main/src/routes`          | Public pages — home, articles, projects, abouts, terminal, sitemap        |
| `main/src/lib/components`  | Shared Svelte components and layout                                       |
| `main/src/lib/server`      | Server-only data access — MySQL, Redis, query helpers                     |
| `admin/app/Http`           | Admin controllers and middleware                                          |
| `admin/app/Services`       | AI generation, web tools, shared content writes, LinkedIn                 |
| `admin/app/Mcp`            | MCP tool definitions and the registry behind `POST /mcp`                  |
| `admin/app/Models`         | Eloquent models; note several map to singular table names (`skill`, …)    |
| `admin/app/Support`        | Standalone helpers with no framework ties — `SafeUrlFetcher`              |
| `admin/resources/views`    | Blade templates for the panel                                             |
| `admin/resources/sass`     | Panel SCSS                                                                |
| `admin/resources/lang`     | Panel translations, 18 locales                                            |
| `admin/database/migrations`| Schema history — the source of truth for column names                     |
| `admin/routes`             | `web.php` (panel), `mcp.php` (MCP endpoint), `console.php` (workers)      |

Both apps read the same database, so a migration in `admin` can break `main`. Check `main/src/lib/server` before renaming or dropping a column.

## Making a change

1. Branch off `master`: `git checkout -b my-change`.
2. Keep the change focused. One concern per pull request.
3. Match the surrounding code — same naming, same idiom, same structure. The existing code is light on comments; make the code and the UI copy explain themselves.
4. Touching panel-facing strings? Add the key to `admin/resources/lang/en`, and delete the keys for any feature you remove.
5. Changing the schema? Add a migration rather than editing an existing one, and check whether `main` reads the affected columns.
6. Adding content fields? Update **both** write paths — the HTTP controller and `ContentWriteService` (used by MCP) — and the full-text index if the field should be searchable by the AI tools.
7. Format and verify before pushing:

```bash
cd main  && npm run build:format && npm run build:sync && npm run build && npx svelte-check
cd admin && vendor/bin/pint && composer test
```

8. Run the app and click through the parts you changed. Screenshots help a lot on UI work.

## Commit messages

Short, imperative subject describing the effect — `Fix project cards rendering an empty image`, not `changes`. Reference an issue with `Fixes #123` when one exists.

## Pull requests

Open it against `master` and include:

- What changed and why, in a couple of sentences.
- How you tested it, including anything you could not test.
- Screenshots for public-site and panel changes.
- A note if it needs a migration, a new `.env` key, or a container restart to take effect.

Draft pull requests are fine for work in progress. Expect review comments — they are about the code, not about you.

## Reporting bugs

Include the version from the README, whether you are on dansday.com or self-hosting, which app is affected (`main` or `admin`), the steps to reproduce, what you expected, what happened, and any relevant log output. Redact tokens, API keys and database credentials.

## Feature requests

Describe the problem before the solution: what you are trying to publish, why the current panel does not cover it, and how you imagine it appearing on the site.
