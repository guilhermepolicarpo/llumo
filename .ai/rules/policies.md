---
paths:
  - 'app/Policies/**'
---

# Policies

## Authorize via Gate::authorize() against Policy classes
Authorization is defined in Policy classes under `app/Policies` and checked at the call site with `Gate::authorize('ability', $model)`. Don't use `$this->authorize()`, `$user->can()`, the `can` middleware, or `@can` in Blade — this app calls `Gate::authorize()` directly, including from inside Livewire components.
