---
paths:
  - 'app/Providers/**'
---

# Providers

## Named RateLimiter::for() limiters
Rate limits are registered as named limiters via `RateLimiter::for('name', ...)` in a service provider's boot method, then referenced by name (`throttle:name`) elsewhere. Don't inline `throttle:60,1` on routes.
