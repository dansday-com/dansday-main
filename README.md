<div align="center">

<img src="main/static/favicon.svg" alt="" width="72">

# &lt;/DANSDAY&gt;

**A terminal-style portfolio you update by talking to Claude.**

Write articles, ship projects and post to LinkedIn from any MCP client. A SvelteKit site that looks like a terminal, a Laravel panel behind it, and an MCP server so your AI assistant does the publishing.

[![License: MIT](https://img.shields.io/badge/license-MIT-1a7f37?style=flat-square)](LICENSE)
[![Self-hostable](https://img.shields.io/badge/self--host-Docker-2b7489?style=flat-square)](#quick-start)
[![MCP tools](https://img.shields.io/badge/MCP-42%20tools-8a63d2?style=flat-square)](#mcp-server)
[![SvelteKit](https://img.shields.io/badge/SvelteKit-2-ff3e00?style=flat-square)](https://kit.svelte.dev/)
[![Laravel](https://img.shields.io/badge/Laravel-12-ff2d20?style=flat-square)](https://laravel.com/)
[![Tests](https://img.shields.io/github/actions/workflow/status/dansday-com/dansday-main/tests.yml?branch=master&style=flat-square&label=tests)](https://github.com/dansday-com/dansday-main/actions/workflows/tests.yml)

**[Live demo](https://dansday.com)** · **[Self-host](#quick-start)** · **[MCP setup](#connect-an-ai-client)** · **[Discussions](https://github.com/dansday-com/dansday-main/discussions)** · **[Contributing](CONTRIBUTING.md)**

![The public site's home page, styled as a terminal window](.github/screenshots/home.png)

</div>

---

## What makes it different

Most portfolios go stale because updating them is a chore. This one has an MCP server built in, so you can tell Claude *"write up the project I just shipped and post it to LinkedIn on Tuesday morning"* and it happens: the article goes on the site, and the post is queued.

- **Your AI client is the editor.** 42 MCP tools cover articles, projects, categories, the about page, site sections and LinkedIn. Claude Code, Claude Desktop, Cursor and any other MCP client can read and write everything.
- **A terminal visitors can talk to.** The `terminal` page answers questions about your work by calling tools against your own content, and searches the live web when you give it a search and fetch gateway.
- **No vector index to babysit.** AI recall runs as tool calls against MySQL full-text search. Nothing to embed, re-index or keep in sync.
- **LinkedIn without the copy-paste.** Post images, carousels, PDFs or video to your feed, schedule them, then edit, react to or delete them later. Commentary goes out exactly as written.
- **Yours to run.** MIT, self-hosted, with no hosted tier and nothing held back.

---

## Quick start

With Docker, `make demo` runs the site, the panel, MySQL and Redis on one machine:

```bash
git clone https://github.com/dansday-com/dansday-main.git
cd dansday-main
make demo
```

The site is on http://localhost:3000 and the panel on http://localhost:8080. `make demo-logs` follows the output and `make demo-down` stops it.

Open the panel, register the first account, and the site seeds itself with sample content to replace.

For production, point `.env` at your own MySQL and Redis, then `make up` builds and starts both apps behind your reverse proxy. `make down` stops them.

To run without Docker, see [CONTRIBUTING.md](CONTRIBUTING.md#local-setup).

### Connect an AI client

Mint a token in the panel, or run `php artisan mcp:token`, then:

```bash
claude mcp add --transport http dansday https://<admin-host>/mcp --header "Authorization: Bearer mcp_live_..."
```

Ask it to list your articles to check it works.

---

## Screenshots

<details>
<summary><b>Public site</b> — articles and live GitHub stats</summary>
<br>

<table>
<tr>
<td width="50%"><img src=".github/screenshots/articles.png" alt="Articles listing in two columns, each card showing title, summary and publish date"></td>
<td width="50%"><img src=".github/screenshots/contribute.png" alt="Contribute page with GitHub stat tiles, a contribution heatmap and live activity"></td>
</tr>
<tr>
<td><strong>Articles</strong> — cards with summary and publish date, filtered by category, sorted server side.</td>
<td><strong>Contribute</strong> — live GitHub stats: commits, PRs, reviews, issues and a contribution heatmap per year.</td>
</tr>
</table>

</details>

<details>
<summary><b>The panel</b> — pages and settings</summary>
<br>

<table>
<tr>
<td width="50%"><img src=".github/screenshots/panel-home.png" alt="Admin panel editing the home page title and description"></td>
<td width="50%"><img src=".github/screenshots/panel-general.png" alt="Admin panel general settings with title, description, analytics ID and social links"></td>
</tr>
<tr>
<td><strong>Pages</strong> — one screen per section: home, abouts, projects, articles.</td>
<td><strong>Settings</strong> — site metadata, analytics, social links, AI providers and section toggles.</td>
</tr>
</table>

</details>

---

## Features

### Public site

- **Articles and projects** - categories, per-item visibility and SEO metadata, sorted server side.
- **About page** - built from skills, experience, services and testimonials, each reorderable.
- **Terminal** - answers questions from your own content through tool calls, and from the live web when search and fetch are configured.
- **Contribute** - live GitHub stats: commits, PRs, reviews, issues and a contribution heatmap per year.
- **Sharing** - Open Graph and Twitter card tags on every page, with a default preview image.
- **Sitemap and `robots.txt`** - generated from live content.

### Panel

- **Full CRUD** - every section of the site, WYSIWYG bodies, optional images.
- **Section toggles** - show or hide whole blocks of the site. Anything switched off disappears from AI recall too.
- **Settings** - site metadata, analytics ID and social links in one place.
- **Multi-language** - panel translations for 18 locales.

### MCP server

- **42 tools over `POST /mcp`** - articles, projects, categories, the about page, site sections and LinkedIn, all readable and writable by an AI client. Bodies are HTML, and `created_at` is writable so posts can be backdated.
- **Per-client tokens** - minted in the panel or with `php artisan mcp:token`. Shown once, stored as a SHA-256 hash, revoked one at a time.
- **File uploads** - `POST /mcp/uploads` takes JPG and PNG to 8MB for site images, PDF, DOC, DOCX, PPT and PPTX to 100MB, and MP4 or MOV to 200MB. The type comes from sniffing the file, never its name. LinkedIn media is stored outside the web root.

### LinkedIn

- **Posts to your personal feed** - one image, 2-20 as a swipeable set, a PDF or slide deck as a carousel, or a video. You write the commentary; nothing is rewritten or summarised.
- **Link placement** - `body`, `card` for a real preview with its own title, description and thumbnail, or `none`. LinkedIn suppresses reach on posts carrying an outbound link, and the usual workaround — dropping it in the first comment — is not possible: commenting through the API needs partner access, which is not self-serve.
- **Post lifecycle** - edit the text of a live post, delete it, or react with any of the six reaction types.
- **Scheduling** - queue a post for later and a worker publishes it. Arguments are validated when you schedule; media uploads at publish time.
- **Panel page** - the connection, token expiry with a warning inside 14 days, the queue, and everything published.

### AI

- **Article and project generation** - against any OpenAI-compatible endpoint, with tool calling so the model searches your existing work before writing.
- **Tool-based recall** - the model queries MySQL full-text search directly, so there is no index to build or keep in sync.
- **Web search and fetch** - optional `search_web` and `fetch_web_page` tools against a gateway's `/search` and `/web/fetch` routes, offered only when their URL, key and model are all set.

---

## Tech stack

Versions match `composer.json` and `package.json` at release.

| Area               | Technologies                                                                                     |
| ------------------ | ------------------------------------------------------------------------------------------------ |
| Frontend           | [SvelteKit](https://kit.svelte.dev/), [Svelte](https://svelte.dev/), [Vite](https://vitejs.dev/)  |
| Language           | [TypeScript](https://www.typescriptlang.org/)                                                    |
| Styling            | [Tailwind CSS](https://tailwindcss.com/) (site), SCSS + Bootstrap (panel)                        |
| Backend            | [Laravel 12](https://laravel.com/) (PHP 8.5+) on [FrankenPHP](https://frankenphp.dev/)            |
| Database           | [MySQL](https://www.mysql.com/), shared by both apps                                             |
| Cache / sessions   | [Redis](https://redis.io/)                                                                       |
| Object storage     | S3-compatible ([Cloudflare R2](https://developers.cloudflare.com/r2/)), local disk for dev       |
| AI providers       | Any OpenAI-compatible chat endpoint, plus optional web search and fetch routes                   |
| AI tooling         | [Model Context Protocol](https://modelcontextprotocol.io)                                        |
| Observability      | [OpenTelemetry](https://opentelemetry.io/)                                                       |
| Infrastructure     | [Docker](https://www.docker.com/), [Docker Compose](https://docs.docker.com/compose/)             |

---

## Configuration

- Copy **`.env.example`** to **`.env`** and set the database and Redis values. The base Compose stack expects your own MySQL and Redis; `make demo` adds both for local use.
- **`APP_URL` must be the admin host exactly.** The LinkedIn callback is derived from it, and article links are built from it with a leading `admin.` stripped.
- **Uploads** default to the local `public/uploads` volume. Set `UPLOADS_DISK_DRIVER=s3` with the `AWS_*` keys to serve them from an S3-compatible bucket instead; keys are stored under `admin/` and `AWS_URL` is the bucket's public base. Point the public site at the same base with `PUBLIC_UPLOADS_URL`. For Cloudflare R2, `AWS_DEFAULT_REGION` must be `auto` and `AWS_USE_PATH_STYLE_ENDPOINT` must be `true`.
- **AI providers, models and prompts** are configured in the panel, not `.env`, and stored per site in the database. Each needs its URL, model and key before it switches on.
- **MCP** lives at the domain root, not under `/admin`, so a path-prefix proxy needs its own `/mcp` rule.
- **LinkedIn** needs an app at [linkedin.com/developers/apps](https://www.linkedin.com/developers/apps) with the **Share on LinkedIn** and **Sign In with LinkedIn using OpenID Connect** products, both self-serve. Register `https://<admin-host>/admin/linkedin/callback`, set `LINKEDIN_CLIENT_ID` and `LINKEDIN_CLIENT_SECRET`, then connect from the panel. Tokens last two months and cannot refresh themselves.
- **Scheduled LinkedIn posts** need `php artisan linkedin:work` running. It runs under supervisord in the Docker image; without it, nothing publishes.

## Contributing

Issues and pull requests are welcome — a typo fix in the panel copy counts. Start with [CONTRIBUTING.md](CONTRIBUTING.md) for local setup and the project layout, and the [Code of Conduct](CODE_OF_CONDUCT.md). Questions and ideas go in [Discussions](https://github.com/dansday-com/dansday-main/discussions).

## Security

Found a vulnerability? Email **security@dansday.com** instead of opening an issue. See [SECURITY.md](SECURITY.md).

---

<div align="center">

**[Live demo](https://dansday.com)** · **[Self-host](#quick-start)** · **[Discussions](https://github.com/dansday-com/dansday-main/discussions)**

MIT · Author: Akbar Yudhanto · Version: 2.5.0

</div>
