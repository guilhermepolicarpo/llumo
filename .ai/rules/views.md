---
paths:
  - 'resources/views/**'
---

# Views

## Interactive elements carry a data-test attribute
Buttons, inputs, and other interactive/assertable elements in Blade views carry a `data-test="descriptive-name"` attribute (e.g. `data-test="team-save-button"`). Add one to new interactive elements so they stay targetable from feature tests.

## Use @can to show or hide what depends on a policy
To decide whether a link, button or section appears, use `@can('ability', $model)` against the same policy ability the target page or action authorizes (e.g. `@can('update', auth()->user()->currentTeam)`). It only controls visibility: the component or action still calls `Gate::authorize()`.
