---
paths:
  - 'app/Rules/**'
---

# Rules

## No Form Requests — rule-set classes + Validator::make()/$this->validate()
This app has no `app/Http/Requests`. Grouped validation rules live as static methods on a dedicated class (e.g. `TeamProfileRules::all()`/`::name()`/`::address()`), called from Livewire's `$this->validate()` or from `Validator::make()` in Actions. Single custom rules implement `ValidationRule` in `app/Rules`.
