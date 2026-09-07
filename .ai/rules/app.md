---
paths:
  - 'app/**'
---

# App

## No PHP Events/Listeners layer
This app has no `app/Events` or `app/Listeners` directories. Actions and models call each other directly rather than dispatching custom PHP events. Cross-component communication instead uses Livewire's own `$this->dispatch('name')` paired with `#[On('name')]` listener methods. Don't introduce an events/listeners layer for decoupling here — match the existing direct-call style.

## Dates: CarbonImmutable + now() helper
`AppServiceProvider` calls `Date::use(CarbonImmutable::class)`, so all date helpers return immutable instances app-wide. Use the `now()`/`today()` helpers for construction rather than `Carbon::now()`/`Carbon::` static calls.
