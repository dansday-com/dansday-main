# &lt;/DANSDAY&gt; Personal Site

A portfolio site that looks like a terminal, and the Laravel panel that runs it. Articles, projects, an about page and live GitHub stats — plus an MCP endpoint so an AI assistant writes and edits your content for you. Self-host from GitHub. MIT licensed.

![The public site's home page, styled as a terminal window](.github/screenshots/home.png)

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

### The panel

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

---

## Features

### Public site

- **Articles and projects** - categories, per-item visibility and SEO metadata, sorted server side.
- **About page** - built from skills, experience, services and testimonials, each reorderable.
- **Terminal** - answers questions from your own content using semantic search.
- **Contribute** - live GitHub stats: commits, PRs, reviews, issues and a contribution heatmap per year.
- **Sitemap and `robots.txt`** - generated from live content.

### Panel

- **Full CRUD** - every section of the site, WYSIWYG bodies, optional images.
- **Section toggles** - show or hide whole blocks of the site. Anything switched off disappears from AI recall too.
- **Settings** - site metadata, analytics ID and social links in one place.
- **Multi-language** - panel translations for 18 locales.

### MCP server

- **47 tools over `POST /mcp`** - articles, projects, categories, the about page and site sections, all readable and writable by an AI client. Bodies are HTML, and `created_at` is writable so posts can be backdated.
- **Per-client tokens** - minted in the panel or with `php artisan mcp:token`. Shown once, stored as a SHA-256 hash, revoked one at a time.
- **File uploads** - `POST /mcp/uploads` takes JPG and PNG to 8MB for site images, PDF, DOC, DOCX, PPT and PPTX to 100MB, and MP4 or MOV to 200MB. The type comes from sniffing the file, never its name. LinkedIn media is stored outside the web root.
- **Embeddings stay in sync** - every write updates the index, so AI recall never goes stale.

### LinkedIn

- **Posts to your personal feed** - one image, 2-20 as a swipeable set, a PDF or slide deck as a carousel, or a video. You write the commentary; nothing is rewritten or summarised.
- **Link placement** - `body`, `card` for a real preview with its own title, description and thumbnail, or `none`. LinkedIn suppresses reach on posts carrying an outbound link, and the usual workaround — dropping it in the first comment — is not possible: commenting through the API needs partner access, which is not self-serve.
- **Post lifecycle** - edit the text of a live post, delete it, or react with any of the six reaction types.
- **Scheduling** - queue a post for later and a worker publishes it. Arguments are validated when you schedule; media uploads at publish time.
- **Panel page** - the connection, token expiry with a warning inside 14 days, the queue, and everything published.

### AI

- **Article and project generation** - against any OpenAI-compatible endpoint, with tool calling so the model searches your existing work before writing.
- **Hybrid retrieval** - MySQL full-text (BM25) fused with embedding similarity via reciprocal rank fusion.
- **Embedding worker** - keeps the index current in the background.

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
| AI providers       | Any OpenAI-compatible chat and embeddings endpoint                                               |
| AI tooling         | [Model Context Protocol](https://modelcontextprotocol.io)                                        |
| Observability      | [OpenTelemetry](https://opentelemetry.io/)                                                       |
| Infrastructure     | [Docker](https://www.docker.com/), [Docker Compose](https://docs.docker.com/compose/)             |

---

## Configuration

- Copy **`.env.example`** to **`.env`** and set the database and Redis values. MySQL and Redis are not in the Compose stack — point them at your own. Then `make up`.
- **`APP_URL` must be the admin host exactly.** The LinkedIn callback is derived from it, and article links are built from it with a leading `admin.` stripped.
- **Uploads** default to the local `public/uploads` volume. Set `UPLOADS_DISK_DRIVER=s3` with the `AWS_*` keys to serve them from an S3-compatible bucket instead; keys are stored under `admin/` and `AWS_URL` is the bucket's public base. Point the public site at the same base with `PUBLIC_UPLOADS_URL`. For Cloudflare R2, `AWS_DEFAULT_REGION` must be `auto` and `AWS_USE_PATH_STYLE_ENDPOINT` must be `true`.
- **AI providers, models and prompts** are configured in the panel, not `.env`, and stored per site in the database. Each needs its URL, model and key before it switches on.
- **MCP** lives at the domain root, not under `/admin`, so a path-prefix proxy needs its own `/mcp` rule. Mint a token in the panel, then point a client at it with `claude mcp add --transport http dansday https://<admin-host>/mcp --header "Authorization: Bearer mcp_live_..."`.
- **LinkedIn** needs an app at [linkedin.com/developers/apps](https://www.linkedin.com/developers/apps) with the **Share on LinkedIn** and **Sign In with LinkedIn using OpenID Connect** products, both self-serve. Register `https://<admin-host>/admin/linkedin/callback`, set `LINKEDIN_CLIENT_ID` and `LINKEDIN_CLIENT_SECRET`, then connect from the panel. Tokens last two months and cannot refresh themselves.
- **Scheduled LinkedIn posts** need `php artisan linkedin:work` running. It runs under supervisord in the Docker image; without it, nothing publishes.

## Security

Found a vulnerability? Email **security@dansday.com** instead of opening an issue. See [SECURITY.md](SECURITY.md).

---

MIT · Author: Akbar Yudhanto · Version: 2.5.0
