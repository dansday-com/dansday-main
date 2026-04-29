# Dansday

Simple monorepo for the public site (`main`, SvelteKit) and the admin panel (`admin`, Laravel) running with Docker Compose.

The project is open source under the MIT license.

---

## Features

### Frontend (public site – `main`)

- **SvelteKit** with **Vite** for fast development and production builds
- **TypeScript** for type safety
- **Tailwind CSS** for styling

### Admin (dashboard – `admin`)

- **Laravel 12** application (PHP 8.5+)
- **SCSS (Sass)** styles in `admin/resources/sass`
- **Bootstrap** (CSS + JS), **jQuery**, **Popper.js**
- **Axios**, **Lodash**, **Font Awesome** assets
- MySQL and Redis integration

### Infrastructure & services

- **Docker** and **Docker Compose** for containerization
- **MySQL** database used by both `main` and `admin`
- **Redis** for cache / queues / sessions (Laravel)
- **Make** commands: `up`, `down`, `install`, `update`

---

## Tech stack

Versions match `package.json` at release (caret ranges; run `npm ls` for the exact tree).

| Area | Technologies |
|------|--------------|
| Frontend framework | [SvelteKit](https://kit.svelte.dev/), [Svelte](https://svelte.dev/), [Vite](https://vitejs.dev/) |
| Language | [TypeScript](https://www.typescriptlang.org/) |
| Styling | [Tailwind CSS](https://tailwindcss.com/) |
| Backend framework | [Laravel 12](https://laravel.com/) (PHP 8.5+) |
| Database | [MySQL](https://www.mysql.com/) via [mysql2](https://github.com/sidorares/node-mysql2) |
| Cache / sessions | [Redis](https://redis.io/) |
| HTTP client | [Axios](https://axios-http.com/) |
| Infrastructure | [Docker](https://www.docker.com/), [Docker Compose](https://docs.docker.com/compose/) |

---

## Configuration

Environment variables drive database credentials, sessions, and service configuration. Copy **`.env.example`** to **`.env`** and adjust for your deployment.

---

## License

MIT · Author: Akbar Yudhanto · Version: 2.3.0
