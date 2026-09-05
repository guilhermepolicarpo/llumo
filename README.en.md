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

> [!WARNING]
> llumo is in early development. The technical foundation — authentication and multi-tenancy — is ready and tested, but **no domain module has been implemented yet**. See the table below and the [roadmap](#roadmap) for what exists today and what is planned.

## Features

| Module | Description | Status |
|---|---|---|
| Centers (multi-tenancy) | A single llumo instance hosts multiple centers, each with isolated data, its own members and roles (Owner/Admin/Member). Email invitations with expiration. | ✅ Ready |
| Accounts and access | Registration, login, email verification, password reset, 2FA (TOTP + recovery codes) and passkeys/WebAuthn. | ✅ Ready |
| Assisted persons (*assistidos*) | Records of the people being assisted, with their session history. | 🚧 Planned |
| Scheduling | Session calendar, with time slots and attendance control. | 🚧 Planned |
| Sessions (*atendimentos*) | Session log, linking the spiritual mentor who acted and the fluidic prescriptions given. | 🚧 Planned |
| Spiritual mentors (*mentores*) | Records of the spiritual mentors, linked to the sessions. | 🚧 Planned |
| Fluidics (*fluídicos*) | Records of fluidics (name and description), prescribed by the mentor during a session. | 🚧 Planned |
| Library | Catalog of the center's books, with loan and return control. | 🚧 Planned |

The core domain flow is: **assisted person → scheduling → session**. The assisted person is registered, a session is scheduled and, in the record of that session, the team notes the spiritual mentor who acted through the medium and the fluidics prescribed. Mentors are protective spirits and guides — more evolved spirits who guide, protect and support a person's moral and spiritual growth, carrying out the spiritual healing during the session. The fluidics stay linked to the session in which they were prescribed, forming the assisted person's history.

## Screenshots

<!-- TODO: add screenshots -->

They will be added as the domain modules are completed.

## Stack

PHP 8.3+ · Laravel 13 · Livewire 4 (single-file components) · Flux UI 2 · Tailwind CSS 4 · Laravel Fortify · Pest 5 · PHPStan level 7 · Pint · SQLite (default) · Vite 8 / vite-plus

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

In the order they should be built:

1. **Assisted persons** — records of the people being assisted
2. **Scheduling** — session calendar
3. **Sessions** — session logging
4. **Mentors and fluidics** — records and their link to sessions
5. **Library** — catalog, loans and returns
6. **Finance** — income and expense control for the center
7. **Social assistance** — management of donations and assistance actions for assisted persons

Beyond the modules, also on the radar:

- **pt-BR translation** — the UI strings are already wrapped in `__()`; what is missing is the `lang/pt_BR.json` file and switching `APP_LOCALE`
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
