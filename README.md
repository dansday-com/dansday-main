## Dansday – Svelte + Laravel (Docker)

Simple monorepo for the public site (`main`, SvelteKit) and the admin panel (`admin`, Laravel) running with Docker Compose.

### First time? What you need

You only need this on your machine:

| Need | Why |
|------|-----|
| **Git** | To clone the repo |
| **Docker** | To run the app and database |
| **Docker Compose** | To start all services (included with Docker Desktop) |
| **Make** | To run `make up` / `make down` (built-in on macOS/Linux; on Windows use [Docker Desktop](https://www.docker.com/products/docker-desktop/) and WSL2 or Git Bash) |

You do **not** need PHP, Node.js, Composer, or npm installed — everything runs inside Docker.

Ports **80**, **8080**, **3306**, and **6379** must be free.

---

### How to run (first time)

**Step 1 – Clone the repo**

```bash
git clone https://github.com/YOUR_USERNAME/dansday-svelte-laravel.git
cd dansday-svelte-laravel
```

**Step 2 – Install dependencies (one time, but safe to re-run)**

This runs inside Docker and will create `vendor/`, `node_modules/`, Laravel `bootstrap/cache`, `storage/*`, and `.env` for the admin app on your machine:

```bash
make install
```

**Step 3 – Start everything**

```bash
make up
```

Then open:

- **Frontend**: http://localhost  
- **Admin**: http://localhost:8080  

**Stop:** `make down`

**Update deps (no need to start app):** `make update`

### Running without Docker (optional)

Only if you run the apps directly on your machine (no Docker): copy `main/.env.example` → `main/.env` and `admin/.env.example` → `admin/.env`, fill in your database and URLs, and for admin run `php artisan key:generate`.

### What’s running (services)

- **main**
  - SvelteKit app
  - Runs in Docker, exposed on port **80** (internal `npm run dev` on port 3000)

- **admin**
  - Laravel 12 application (PHP 8.5+)
  - Served on port **8080**

- **mysql**
  - MySQL database
  - Used by both `main` and `admin`

- **redis**
  - Redis for cache / queues / sessions (Laravel)

### Tech stack

- **Frontend (public site – `main`)**
  - **SvelteKit**, **Vite**
  - **TypeScript**
  - **Tailwind CSS**

- **Admin (dashboard – `admin`)**
  - **Laravel 12** (PHP 8.5+)
  - **SCSS (Sass)** styles in `admin/resources/sass`
  - **Bootstrap** (CSS + JS), **jQuery**, **Popper.js**
  - **Axios**, **Lodash**, **Font Awesome** assets
  - MySQL, Redis

- **Infrastructure / Tooling**
  - **Docker**, **Docker Compose**
  - Make: `up`, `down`, `install`, `update`
