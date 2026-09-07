---
paths:
  - 'tests/**'
---

# Tests

## Use Pest's test(), not it()
Every test in this suite uses the `test('description', ...)` function. Don't use `it('...', ...)` — it's unused across the whole suite.

## No Mockery — test against real collaborators, fake only outbound HTTP
Tests never mock/spy internal classes (Actions, models) with Mockery — they exercise the real objects against a real (RefreshDatabase) database. The only faked boundary is outbound HTTP via `Http::fake([...])` (see TeamProfileTest for the postal-code lookup). Don't introduce `->mock()`/`->spy()`/`Mockery::` for internal collaborators; test them for real.
