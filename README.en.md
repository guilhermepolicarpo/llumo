<p align="center">
  <img src="art/logo-wordmark.svg" width="180" alt="llumo">
</p>

<p align="center">
  Management for Spiritist centers — free, self-hosted and built with care.
</p>

<p align="center">
  <a href="README.md">Leia em português</a>
</p>

<p align="center">
  <a href="https://github.com/guilhermepolicarpo/llumo/actions/workflows/tests.yml"><img src="https://github.com/guilhermepolicarpo/llumo/actions/workflows/tests.yml/badge.svg" alt="CI"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="MIT license"></a>
  <img src="https://img.shields.io/badge/PHP-8.3%2B-777BB4" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20" alt="Laravel 13">
</p>

## About

llumo is a management system for Spiritist centers: records of assisted persons (*assistidos*), scheduling and logging of spiritual sessions, library control — the daily life of the center in one place.

The name comes from Esperanto *lumo* (light), a language with a historical connection to the Spiritist movement. It is always written in lowercase.

Two principles guide the project:

- **The center's data belongs to the center.** llumo is self-hosted: you install it on your own server and keep full control over the information of the people you assist.
- **Free software, no subscription.** Open source under the MIT license, with no license fees and no vendor lock-in.

> [!NOTE]
> llumo is in active development. The modules for the center's daily work — assisted persons, scheduling, sessions and library — already work and are covered by tests, but there is no stable release yet. See the [roadmap](#roadmap) for what comes next.

## Features

| Module | Description | Status |
|---|---|---|
| Centers (multi-tenancy) | A single llumo instance hosts multiple centers, each with isolated data, its own members and roles (Owner/Admin/Member). Email invitations with expiration. In **Center settings**, admins manage the center's details (name, logo, phone, address filled in from the postal code), its members and its catalogs. | ✅ Ready |
| Accounts and access | Registration, login, email verification, password reset, 2FA (TOTP + recovery codes) and passkeys/WebAuthn. | ✅ Ready |
| Assisted persons (*assistidos*) | Records of the people being assisted, with contact details and address (filled in from the postal code), search, and each person's session and loan history. | ✅ Ready |
| Scheduling | Daily schedule, in-person or remote sessions, arrival check-in and waiting queue, filters by status, type and attendant, and missed sessions marked automatically. Session types with weekdays and a daily limit. | ✅ Ready |
| Sessions (*atendimentos*) | Session record: mentor, fluidics (quantity and dosage), passes, recommended guidance, infiltration removal, a scheduled return and notes, with a draft saved while filling it in. The day's attendance sheets print as a PDF. | ✅ Ready |
| Catalogs | Session types, spiritual mentors (*mentores*), fluidics (*fluídicos*), guidance and pass types; for the library, authors, publishers, categories and collections. | ✅ Ready |
| Library | The center's books, looked up by ISBN in public catalogs (BrasilAPI and Open Library, with cover), copies with a shelf code, loans, renewals and returns, overdue tracking and loan rules set by each center. | ✅ Ready |
| Dashboard | An overview of the center's day. | 🚧 Planned |
| Finance | Income and expense control for the center. | 🚧 Planned |
| Social assistance | Management of donations and assistance actions for assisted persons. | 🚧 Planned |

The core domain flow is: **assisted person → scheduling → session**. The assisted person is registered, a session is scheduled and, in the record of that session, the team notes the spiritual mentor who acted through the medium and the fluidics prescribed. Mentors are protective spirits and guides — more evolved spirits who guide, protect and support a person's moral and spiritual growth, carrying out the spiritual healing during the session. The fluidics stay linked to the session in which they were prescribed, forming the assisted person's history.

## Screenshots

<p align="center">
  <img src="art/screenshots/agendamentos.png" alt="llumo's scheduling screen: the day's schedule with search, filters, a notice about sessions awaiting a record, and the list of assisted persons with the Check in and Attend actions">
</p>

<p align="center"><em>Scheduling: the day's schedule, with check-in and the session for each assisted person (interface in Brazilian Portuguese).</em></p>

## Stack

PHP 8.3+ · Laravel 13 · Livewire 4 (single-file components) · Flux UI 2 · Tailwind CSS 4 · Laravel Fortify · Laravel PDF (DomPDF) · Pest 5 · PHPStan level 7 · Pint · SQLite (default) · Vite 8 / vite-plus

## Requirements

- PHP 8.3 or higher (CI runs on 8.5)
- Composer
- Node.js 22 or higher
- A database — SQLite by default, no external service required

MySQL and PostgreSQL also work: adjust `DB_CONNECTION` and the credentials in `.env`.

## Installation

```bash
git clone https://github.com/guilhermepolicarpo/llumo.git
cd llumo
composer setup
composer dev
```

`composer setup` takes care of everything: it installs the PHP dependencies, creates `.env` from `.env.example`, generates the application key, runs the migrations, installs the JS dependencies and builds the assets.

`composer dev` starts the server, queue worker, logs and Vite together (via `php artisan dev`). The application is then available at `http://localhost:8000`.

If you use [Herd](https://herd.laravel.com), you can skip the built-in server: point Herd at the project directory, adjust `APP_URL` in `.env` and run Vite only.

### Demo data

To explore llumo with sample data, run `php artisan db:seed`. It creates the user `test@example.com` (password `password`) with a center already filled in: assisted persons, scheduling, sessions, catalogs and the library with books and loans.

### In production

- **Scheduler:** set up Laravel's cron entry (`* * * * * php artisan schedule:run`). It marks sessions from previous days that were never attended as missed and deletes expired invitations.
- **Queues:** keep a worker running (`php artisan queue:work`).
- **External services:** looking up an address by postal code and a book by ISBN uses the free public APIs of [BrasilAPI](https://brasilapi.com.br) and [Open Library](https://openlibrary.org). Their URLs can be changed with `BRASIL_API_URL` and `OPEN_LIBRARY_URL` in `.env`.
- **View cache:** run `php artisan view:cache` (or `view:clear`) on every deploy, so the translations of the Flux components make it into the compiled views.

The interface is in Brazilian Portuguese (`APP_LOCALE=pt_BR`).

## Development

| Command | What it does |
|---|---|
| `composer dev` | Server, queues, logs and Vite |
| `composer test` | Full suite: Pint (check), PHPStan and Pest |
| `composer lint` | Formats the code with Pint |
| `composer types:check` | Static analysis (PHPStan level 7) |
| `php artisan test --compact` | Tests only |

`composer test` is exactly what CI runs on every push and pull request. Run it before opening a PR.

## Roadmap

Already done: assisted persons, scheduling, sessions, mentors and fluidics, the library, and the pt-BR translation of the interface.

Next:

1. **Dashboard** — an overview of the day: scheduled sessions, sessions awaiting a record and overdue loans
2. **Finance** — income and expense control for the center
3. **Social assistance** — management of donations and assistance actions for assisted persons

Beyond the modules, also on the radar:

- **Docker image and deployment guide** — to make self-hosting as simple as possible

## Contributing

Contributions are very welcome. See [CONTRIBUTING.md](CONTRIBUTING.md) for local setup, coding standards and the pull request workflow.

By participating in this project, you agree to abide by our [Code of Conduct](CODE_OF_CONDUCT.md).

## Security

Found a vulnerability? Please do not open a public issue. Report it through the repository's [GitHub Security Advisories](https://github.com/guilhermepolicarpo/llumo/security/advisories/new).

## Community

- [Issues](https://github.com/guilhermepolicarpo/llumo/issues) — bugs and feature requests
- [Discussions](https://github.com/guilhermepolicarpo/llumo/discussions) — questions, ideas and general conversation

## License

llumo is free software under the [MIT](LICENSE) license.
