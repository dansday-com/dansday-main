## Dansday – Svelte + Laravel (Docker)

Simple monorepo for the public site (`main`, SvelteKit) and the admin panel (`admin`, Laravel) running with Docker Compose.

### How to run with Docker

- **Prerequisites**
  - **Docker** and **Docker Compose** installed
  - Ports **80**, **8080**, **3306**, and **6379** free on your machine

- **First time setup (installs dependencies)**

```bash
make install
```

- **Start the stack**

```bash
make up
```

Then open:

- **Frontend (SvelteKit)**: `http://localhost`
- **Admin panel (Laravel)**: `http://localhost:8080`

- **Stop everything**

```bash
make down
```

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

