'use strict';
(() => {
    const canvas = document.getElementById('cm-signature');
    if (!canvas) { return; }
    const context = canvas.getContext('2d');
    const input = document.getElementById('cm-signature-data');
    let strokes = [];
    let current = null;
    let count = 0;
    const updateInput = () => {
        input.value = strokes.some((stroke) => stroke.length > 1) ? JSON.stringify(strokes) : '';
        input.dispatchEvent(new Event('input', {bubbles: true}));
    };
    const point = (event) => {
        const rect = canvas.getBoundingClientRect();
        return [Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width)),
            Math.max(0, Math.min(1, (event.clientY - rect.top) / rect.height))];
    };
    canvas.addEventListener('pointerdown', (event) => {
        if (count >= 3900) { return; }
        canvas.setPointerCapture(event.pointerId);
        current = [point(event)];
        strokes.push(current);
        count++;
    });
    canvas.addEventListener('pointermove', (event) => {
        if (!current || count >= 4000) { return; }
        const previous = current[current.length - 1];
        const next = point(event);
        if (previous[0] === next[0] && previous[1] === next[1]) { return; }
        context.beginPath();
        context.lineWidth = 2;
        context.moveTo(previous[0] * canvas.width, previous[1] * canvas.height);
        context.lineTo(next[0] * canvas.width, next[1] * canvas.height);
        context.stroke();
        current.push(next);
        count++;
        updateInput();
    });
    const stop = () => { current = null; updateInput(); };
    canvas.addEventListener('pointerup', stop);
    canvas.addEventListener('pointercancel', stop);
    document.getElementById('cm-signature-clear').addEventListener('click', () => {
        strokes = [];
        current = null;
        count = 0;
        updateInput();
        context.clearRect(0, 0, canvas.width, canvas.height);
    });
})();
