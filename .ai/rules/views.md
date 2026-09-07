---
paths:
  - 'resources/views/**'
---

# Views

## Interactive elements carry a data-test attribute
Buttons, inputs, and other interactive/assertable elements in Blade views carry a `data-test="descriptive-name"` attribute (e.g. `data-test="team-save-button"`). Add one to new interactive elements so they stay targetable from feature tests.
