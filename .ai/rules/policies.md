---
paths:
  - 'app/Policies/**'
---

# Policies

## Authorize via Gate::authorize() against Policy classes
Authorization is defined in Policy classes under `app/Policies` and checked at the call site with `Gate::authorize('ability', $model)`. Don't use `$this->authorize()`, `$user->can()` or the `can` middleware — this app calls `Gate::authorize()` directly, including from inside Livewire components.

`@can` / `@cannot` are allowed in Blade views, but only to decide what to show (a link, a button, a section). They never replace the `Gate::authorize()` check in the component or action that performs the work.
