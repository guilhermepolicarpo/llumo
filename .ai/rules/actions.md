---
paths:
  - 'app/Actions/**'
---

# Actions

## Action classes expose handle(), wired by injection
Business logic lives in plain Action classes under `app/Actions/**`, one action per class, with the entry point named `handle()`. Actions are resolved via constructor injection (into other actions/providers) or method injection (Livewire component methods), never via `app()`/`resolve()` service location.

## Wrap multi-step writes in DB::transaction()
Actions that write more than one row (or a row plus a side effect like storing a file) wrap the work in `DB::transaction(function () { ... })`, not manual `beginTransaction`/`commit`/`rollBack`.
