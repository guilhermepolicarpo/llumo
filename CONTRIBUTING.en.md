# Contributing to llumo

> [Leia em português](CONTRIBUTING.md)

Thank you for your interest in contributing. llumo is a project aimed at Spiritist centers, and any help — code, translation, documentation, bug reports, or simply telling us how your center works — is welcome.

By participating in this project, you agree to abide by our [Code of Conduct](CODE_OF_CONDUCT.en.md).

## Before you start

The project is in early development and follows a [defined roadmap](README.en.md#roadmap). Before investing time in code:

- **For a bug**, open an [issue](https://github.com/guilhermepolicarpo/llumo/issues/new/choose) with reproduction steps.
- **For a new feature**, open a [discussion](https://github.com/guilhermepolicarpo/llumo/discussions) first. Talking it through avoids you writing something that does not fit the project's direction — which is frustrating for everyone.
- **For a small, obvious fix** (typo, broken link, wrong copy), send the pull request directly.

## Development environment

Requirements: **PHP 8.3+** (CI runs on 8.5), **Composer** and **Node.js 22+**. The default database is SQLite, so no external service is needed.

```bash
git clone https://github.com/guilhermepolicarpo/llumo.git
cd llumo
composer setup
composer dev
```

`composer setup` installs dependencies, creates `.env`, generates the key, runs migrations and builds assets. `composer dev` starts the server, queue, logs and Vite at once.

## Code standards

The project is strict about style and static analysis, because CI blocks the merge if anything fails.

| Command | What it does |
| --- | --- |
| `composer lint` | Formats the code with Pint (`laravel` preset) |
| `composer types:check` | Static analysis with PHPStan **level 7** |
| `composer test` | Runs all three: Pint, PHPStan and Pest |

Conventions worth knowing before your first PR:

- **Code, names and commit messages in English.** The interface is translated, the code is not.
- **The project name is `llumo`**, always lowercase — including at the start of a sentence.
- **Always use explicit types**: return type and type hint on every parameter.
- **Curly braces on every control structure**, even single-line ones.
- **PHP 8 constructor property promotion** instead of manual assignment.
- **PHPDoc instead of inline comments.** Inline comments only for genuinely difficult logic.
- **Livewire 4 components are single-file**, created under `resources/views/` with the `⚡` prefix in the filename — for example `resources/views/pages/settings/⚡profile.blade.php`. Only create a class in `app/Livewire/` when there is a concrete reason.
- **Every UI string wrapped in `__()`.** The project still runs in English, but the Portuguese translation depends on this.
- **Use the Artisan generators** (`php artisan make:...`) instead of creating files by hand.

[CLAUDE.md](CLAUDE.md) details these conventions and is the reference used by AI agents working on the repository.

## Tests

Every new behavior needs a test. The project uses **Pest 5**, with a strong preference for feature tests:

```bash
php artisan make:test --pest TestName          # feature
php artisan make:test --pest --unit TestName

php artisan test --filter=testName             # run the narrowest set possible
php artisan test --compact                     # full suite
```

Use the existing factories (`UserFactory`, `TeamFactory`, `TeamInvitationFactory`) and check whether a state already covers your case before building the model by hand.

## Domain terminology

llumo deals with concepts from Spiritist doctrine, and using the wrong term in code or in the interface creates real confusion for the people using the system. A summary of the vocabulary:

- **Assistido** (assisted person) — the person who receives care at the center.
- **Atendimento** (session) — the meeting in which the assisted person is attended to.
- **Mentor** (spiritual mentor) — the spirit guide who advises and performs the spiritual treatment through the medium. It is **not** an incarnate volunteer.
- **Fluídico** (fluidic prescription) — the prescription given by the mentor during a session, recorded with a name and description and linked to that session.

If you are not familiar with these concepts, that is fine — ask in the issue or discussion. Better to ask than to assume.

## Opening the pull request

1. Branch off `main`.
2. **One subject per PR.** Mixing a refactor with a new feature makes review much harder.
3. Run `composer test` before submitting — it is exactly what CI runs, so what passes here passes there.
4. Describe **what** changes and **why**. For visual changes, attach a screenshot.
5. If the PR closes an issue, reference it with `Closes #123`.

Reviews may take a few days. If nobody replies within a week, feel free to ping on the PR itself.

## Security

Found a vulnerability? **Do not open a public issue.** Report it through the repository's [GitHub Security Advisories](https://github.com/guilhermepolicarpo/llumo/security/advisories/new).

## License

By contributing, you agree that your contribution will be licensed under the project's [MIT license](LICENSE).
