---
paths:
  - 'app/Data/**'
---

# Data

## DTOs are plain readonly classes
Data-transfer objects in `app/Data/**` are plain `readonly class`es with constructor-promoted properties and no behavior beyond the constructor. spatie/laravel-data is not installed — do not reach for `extends Data` or attribute-based DTOs.
