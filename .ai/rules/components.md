---
paths:
  - 'resources/views/components/**'
---

# Components

## Livewire components use native single-file format, not Volt
Livewire components (including nested modals under `components/`) are native Livewire 4 single-file components: `new class extends Component { ... }; ?>` followed by Blade markup, in one file, filename prefixed with `⚡`. Do not use Volt or split into class+view (MFC).

## Use plain make:livewire (no namespace) for nested/reusable components
When a Livewire component is not a full page — it will be inserted into a page or another component (e.g. a modal, a nested widget) — create it without a namespace: `php artisan make:livewire assisted-people.index`. This generates the file at `resources/views/components/assisted-people/⚡index.blade.php`. Only use the `pages::` namespace for full-page, routed components.
