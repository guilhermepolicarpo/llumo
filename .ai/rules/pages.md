---
paths:
  - 'resources/views/pages/**'
---

# Pages

## Livewire components use native single-file format, not Volt
Full-page and nested Livewire components are native Livewire 4 single-file components: `new class extends Component { ... }; ?>` followed by the Blade markup, in one file. Filenames are prefixed with `⚡`. Do not use Volt syntax (`@volt`) or split into a separate class + view (MFC) — this app uses neither.
