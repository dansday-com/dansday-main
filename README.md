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

**You do not need to create any `.env` files.** All config is provided by Docker Compose.

```bash
git clone https://github.com/YOUR_USERNAME/dansday-svelte-laravel.git
cd dansday-svelte-laravel
make up
```

First run will build images and install dependencies (a few minutes). Then open:

- **Frontend**: http://localhost  
- **Admin**: http://localhost:8080  

**Stop:**

```bash
make down
```

**Optional** — install or update dependencies without starting the app:

```bash
make install   # install deps
make update    # update deps
```

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
  - `Makefile` helpers (`make up`, `make down`, `make install`)

