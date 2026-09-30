# Security Policy

## Reporting a vulnerability

Email **security@dansday.com**. Do not open a public issue or pull request for a security problem.

Please include:

- What the issue is and where in the code it lives — the public site (`main`), the admin panel (`admin`), or the MCP endpoint.
- Steps to reproduce, or a short proof of concept.
- What an attacker gains — data read, privileges gained, content modified, service taken down.
- Whether you tested dansday.com or a self-hosted install, and which version.

You will get a first reply within 72 hours. Valid reports get a fix timeline in that reply, and credit in the release notes unless you ask otherwise.

## Scope

In scope:

- This repository — the SvelteKit public site, the Laravel admin panel, the MCP tool layer and the database access in both apps.
- dansday.com and its admin panel.

Out of scope:

- Anything needing an admin session or an MCP token that you already own. An MCP token is designed to grant full content read and write, including deletes — using one as intended is not a vulnerability.
- Self-hosted installs misconfigured against the guidance below.
- Findings from an automated scanner with no working exploit, including dependency alerts already listed in this repository's Dependabot page.
- Any third-party AI, web search or web fetch provider you configure in the panel — report those to the vendor.
- Rate limits, missing headers or version disclosure with no demonstrated impact.

## Testing rules

Test only against your own self-hosted install. Do not touch other people's data, and do not run denial-of-service or automated scans against dansday.com.

## Supported versions

Fixes ship on the latest release only. There are no long-term support branches — update to the newest tag before reporting.

## Self-hosting

You own the security of your own deployment:

- Keep `.env` out of version control. It holds `APP_KEY`, database credentials, Redis credentials and `LINKEDIN_CLIENT_SECRET`.
- Put both apps behind HTTPS. The panel authenticates with a session cookie and the MCP endpoint with a bearer token — over plain HTTP both travel in the clear.
- Do not expose the MySQL or Redis port to the internet. Both `main` and `admin` connect directly to MySQL.
- Give each app its own database user, not root.
- **Provider keys live in the database**, in the `page_setting` row, not `.env` — AI, web search and web fetch keys alongside the LinkedIn access token in plaintext. Treat the database as credential storage and restrict access accordingly.
- The AI, web search and web fetch endpoints you configure in the panel are fetched by the server. Only point them at hosts you trust. The fetch tool asks that gateway to read any URL the model picks, so the gateway, not this server, must be the one that blocks internal addresses.
- **An MCP token is a password.** It grants full write access to your content, can publish and delete on LinkedIn under your name, and can write a file to your public web root. Mint one per client so you can revoke one without breaking the rest, and audit with `php artisan mcp:token --list`.
- Tokens are stored as SHA-256 hashes, so a database read does not hand over usable tokens — but the same row holds the provider keys above. `confirm=true` on the LinkedIn tools guards against accident, not against a hostile client.
- Only posts and comments this server made can be edited or deleted. LinkedIn will not let it enumerate the rest of your feed, which bounds what a leaked token can reach there. Revoke LinkedIn access from LinkedIn's own **Settings → Data privacy → Permitted services**; clearing the `linkedin_*` columns only stops this app using the token.
- Scheduled posts publish unattended. They are replayed through the same validation as a live post, but anyone who can write a row can publish under your name at a time of their choosing.
- The MCP endpoint bypasses the panel's `XSS` middleware by design, because that middleware mangles escaped angle brackets in code samples. Article and project bodies are stripped of `script`, `style`, `iframe`, `object`, `embed` and `form` elements, inline `on*` handlers and `javascript:` URLs. Everything else written through MCP renders as raw HTML on the public site.
- **Uploads are a write path.** Site images land in `admin/public/uploads` and are served as-is; documents and video land on the `media` disk outside the web root and are never served. Do not move that disk under `public/`. The stored extension comes from sniffing the file, never the client's filename, and filenames are always regenerated.
- Upload paths are normalised segment by segment rather than by stripping `../`, which a payload like `....//` defeats, and reads are confined with `realpath`. `UploadPathSafetyTest` and `McpUploadTest` pin this — keep them passing. Raising the size ceilings means raising `upload_max_filesize` and `post_max_size` to match, and nothing prunes `storage/app/media`.
- Fetching a post image by URL makes the server issue an outbound request. It is restricted to `http` and `https`, no credentials in the URL, every resolved address must be public, redirects are refused and the body is capped. DNS rebinding is not covered.
- Run `composer audit` and `npm audit` after pulling, and update dependencies.
