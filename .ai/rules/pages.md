---
paths:
  - 'resources/views/pages/**'
---

# Pages

## Livewire components use native single-file format, not Volt
Full-page and nested Livewire components are native Livewire 4 single-file components: `new class extends Component { ... }; ?>` followed by the Blade markup, in one file. Filenames are prefixed with `⚡`. Do not use Volt syntax (`@volt`) or split into a separate class + view (MFC) — this app uses neither.

## Use make:livewire pages:: namespace for full-page components
When a Livewire component is a full page (routed directly), create it with the `pages::` namespace: `php artisan make:livewire pages::assisted-people.index`. This generates the file at `resources/views/pages/assisted-people/⚡index.blade.php`, matching this app's directory structure. Do not run `make:livewire assisted-people.index` without the namespace for page components — that targets `resources/views/components/` instead.
