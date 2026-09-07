---
paths:
  - 'routes/**'
---

# Routes

## No controllers — routes point at Livewire full-page components
This app has no resource/multi-method controllers. Register full pages with `Route::livewire('path', 'view.name')` or `Route::view()`, not a controller. Business logic belongs in the Livewire component plus Action classes, never a controller method.
