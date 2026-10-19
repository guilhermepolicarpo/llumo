---
paths:
  - 'resources/views/**'
---

# Views

## Interactive elements carry a data-test attribute
Buttons, inputs, and other interactive/assertable elements in Blade views carry a `data-test="descriptive-name"` attribute (e.g. `data-test="team-save-button"`). Add one to new interactive elements so they stay targetable from feature tests.

## Use @can to show or hide what depends on a policy
To decide whether a link, button or section appears, use `@can('ability', $model)` against the same policy ability the target page or action authorizes (e.g. `@can('update', auth()->user()->currentTeam)`). It only controls visibility: the component or action still calls `Gate::authorize()`.

## Flux icons always use the outline variant
Never use the filled `solid`, `mini` or `micro` icon variants. On `<flux:icon.*>` leave `variant` out (outline is the default) and size it with a class: `size-4` (16px) or `size-5` (20px), e.g. `<flux:icon.clock class="size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />`. On `<flux:button icon="...">` the default is filled, so add `icon:variant="outline"`; the button sizes the icon itself. Exception: the `plus` icon on primary "Novo ..." buttons keeps the button's default variant. Check the Flux docs with `search-docs` before using a component instead of inferring its API from vendor code.

## Build custom UI only where Flux has nothing ready
Design screens and mockups with the Flux v2 components and their anatomies first; confirm whether an equivalent exists in the Flux docs (`search-docs`) or `vendor/livewire/flux*/stubs/resources/views/flux/`. Build a custom component only when Flux offers nothing ready for the need, and keep it visually consistent with Flux. Never rebuild or restyle something Flux already provides. Fields follow label → description → control → error: `flux:description` goes above or below the input, never on the label's line or beside the input. Unit suffixes use `flux:input.group.suffix`; "opcional" uses the label's `badge`. Keep the app's colors: `resources/css/app.css` remaps `zinc-*` to `slate-*`, and the accent is `blue-500` for button backgrounds and `blue-600` for text.
