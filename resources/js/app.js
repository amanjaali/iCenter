/*
| Livewire 3 bundles and starts Alpine itself, so Alpine is deliberately not
| imported here — a second instance would fight the first over the same global
| and directives would silently stop working. Alpine's directives used across
| these views (x-data, x-show, x-cloak, x-teleport) come from Livewire's copy,
| loaded by @livewireScripts in the layout.
*/

// x-cloak needs a style rule to do anything; Tailwind does not ship one.
const style = document.createElement('style');
style.textContent = '[x-cloak]{display:none !important}';
document.head.appendChild(style);

/*
| Confirm-on-submit for plain forms that are not worth a modal. Any form
| carrying data-confirm asks before it posts.
*/
document.addEventListener('submit', (event) => {
    const message = event.target?.dataset?.confirm;

    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

/*
| Keep a table's "select all" checkbox in step with its rows.
*/
document.addEventListener('change', (event) => {
    const master = event.target.closest('[data-select-all]');

    if (!master) {
        return;
    }

    const scope = document.querySelector(master.dataset.selectAll);

    scope?.querySelectorAll('input[type="checkbox"]').forEach((box) => {
        box.checked = master.checked;
    });
});
