'use strict';
(() => {
    const method = document.getElementById('cm-method');
    const drawnControls = document.getElementById('cm-drawn-controls');
    if (!method || !drawnControls) { return; }

    const input = document.getElementById('cm-signature-data');
    const error = document.getElementById('cm-signature-error');
    const canvas = document.getElementById('cm-signature');
    const updateMethod = () => {
        drawnControls.hidden = method.value !== 'drawn';
        error.hidden = true;
    };
    method.addEventListener('change', updateMethod);
    updateMethod();
    input.addEventListener('input', () => { if (input.value !== '') { error.hidden = true; } });
    method.closest('form').addEventListener('submit', (event) => {
        if (method.value === 'drawn' && input.value === '') {
            event.preventDefault();
            error.hidden = false;
            canvas.focus();
            canvas.scrollIntoView({block: 'center', behavior: 'smooth'});
        }
    });
})();
