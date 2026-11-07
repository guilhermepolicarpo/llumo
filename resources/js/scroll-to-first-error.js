/**
 * Livewire does not bring validation errors into view. When a click or a form submit fails validation in this request
 * (the server dispatches "validation-failed") and the first error is off screen, scroll its field into view and focus
 * it. Errors kept from an earlier request do not move the page when the person clicks something else.
 */
export default function registerScrollToFirstError(Livewire) {
    Livewire.interceptMessage(({ message, onSuccess }) => {
        onSuccess(({ payload, onRender }) => {
            if (! payload.effects?.dispatches?.some((event) => event.name === 'validation-failed')) {
                return;
            }

            if ([...message.actions].some((action) => ['click', 'submit'].includes(action.origin?.directive?.value))) {
                onRender(() => revealFirstError(message.component.el));
            }
        });
    });
}

function revealFirstError(root) {
    const error = [...root.querySelectorAll('[data-flux-error]:not(.hidden)')].find((element) => element.offsetParent !== null);

    if (! error) {
        return;
    }

    const field = error.closest('[data-flux-field]') ?? error.parentElement;
    const { top, bottom } = field.getBoundingClientRect();

    if (top >= 0 && bottom <= window.innerHeight) {
        return;
    }

    field.scrollIntoView({ behavior: 'smooth', block: 'center' });
    field.querySelector('input:not([type=hidden]), textarea, select, button')?.focus({ preventScroll: true });
}
