---
paths:
  - 'resources/views/components/**'
---

# Components

## Livewire components use native single-file format, not Volt
Livewire components (including nested modals under `components/`) are native Livewire 4 single-file components: `new class extends Component { ... }; ?>` followed by Blade markup, in one file, filename prefixed with `⚡`. Do not use Volt or split into class+view (MFC).
