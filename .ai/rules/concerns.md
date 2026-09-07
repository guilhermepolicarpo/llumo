---
paths:
  - 'app/Concerns/**'
---

# Concerns

## Shared validation rule sets live in Concerns traits
Reusable validation rule groups (e.g. `PasswordValidationRules::passwordRules()`, `ProfileValidationRules::profileRules()`) are traits under `app/Concerns`, mixed into Action classes and called into `Validator::make()`. Follow this shape for new shared rule sets rather than a Form Request or inline array duplication.
