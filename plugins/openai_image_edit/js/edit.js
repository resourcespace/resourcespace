const canvas = document.getElementById('canvas');
const ctx = canvas.getContext('2d');

const overlayCanvas = document.getElementById('overlayCanvas');
const overlayCtx = overlayCanvas.getContext('2d');

const image = document.getElementById('image');

let drawing = false;
let penSize = document.getElementById('penSize').value;
let lastX = 0;
let lastY = 0;

// Holds the full-resolution OpenAI result for export
let outputImageDataURL = null;

image.addEventListener('load', drawImageOnCanvas, { once: true });

function resetCanvasContext() {
    ctx.globalCompositeOperation = 'source-over';
    ctx.shadowColor = 'transparent';
    ctx.shadowBlur = 0;
    ctx.shadowOffsetX = 0;
    ctx.shadowOffsetY = 0;
    ctx.setLineDash([]);
}

function drawImageOnCanvas() {
    canvas.width = image.naturalWidth || image.width;
    canvas.height = image.naturalHeight || image.height;

    overlayCanvas.width = canvas.width;
    overlayCanvas.height = canvas.height;

    resetCanvasContext();
    ctx.drawImage(image, 0, 0, canvas.width, canvas.height);

    document.getElementById('canvas-container').style.visibility = 'visible';
    document.getElementById('toolbox').style.visibility = 'visible';
    CentralSpaceHideProcessing();
    HideThumbs();
}

// Adjust pen size
document.getElementById('penSize').addEventListener('input', function () {
    penSize = this.value;
});

// Start drawing
canvas.addEventListener('mousedown', (e) => {
    drawing = true;
    [lastX, lastY] = getMousePos(e);
    draw(e);
});

canvas.addEventListener('mouseup', () => drawing = false);
canvas.addEventListener('mousemove', draw);

function draw(e) {
    if (document.getElementById('penSize').disabled) return false;

    const [x, y] = getMousePos(e);
    const mode = document.getElementById('editMode').value;

    e.preventDefault();

    overlayCtx.clearRect(0, 0, overlayCanvas.width, overlayCanvas.height);

    if (!drawing) {
        overlayCtx.setLineDash([5, 5]);
        overlayCtx.lineWidth = 2;

        overlayCtx.strokeStyle = 'white';
        overlayCtx.beginPath();
        overlayCtx.arc(x, y, penSize / 2, 0, Math.PI * 2);
        overlayCtx.stroke();

        overlayCtx.strokeStyle = 'black';
        overlayCtx.lineWidth = 1;
        overlayCtx.beginPath();
        overlayCtx.arc(x, y, penSize / 2, 0, Math.PI * 2);
        overlayCtx.stroke();

        return;
    }

    ctx.globalCompositeOperation = 'destination-out';
    ctx.lineWidth = penSize;
    ctx.strokeStyle = 'black';
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    if (mode === 'clone' || mode === 'white' || mode === 'black') {
        ctx.lineWidth = penSize / 3;
        ctx.shadowColor = 'black';
        ctx.shadowBlur = penSize / 2;
    } else {
        ctx.shadowColor = 'transparent';
        ctx.shadowBlur = 0;
    }

    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(x, y);
    ctx.stroke();

    [lastX, lastY] = [x, y];
}

// Get mouse position relative to the canvas
function getMousePos(e) {
    const rect = canvas.getBoundingClientRect();

    return [
        (e.clientX - rect.left) * (canvas.width / rect.width),
        (e.clientY - rect.top) * (canvas.height / rect.height)
    ];
}

// Hide brush preview
canvas.addEventListener('mouseleave', () => {
    overlayCtx.clearRect(0, 0, overlayCanvas.width, overlayCanvas.height);
    drawing = false;
});

// Submit canvas
document.getElementById('submitBtn').addEventListener('click', async () => {

    const displayWidth = canvas.width;
    const displayHeight = canvas.height;

    const tempCanvas = document.createElement('canvas');
    const tempCtx = tempCanvas.getContext('2d');

    tempCanvas.width = displayWidth;
    tempCanvas.height = displayHeight;

    resetCanvasContext();
    tempCtx.drawImage(canvas, 0, 0, displayWidth, displayHeight);

    const mask = tempCanvas.toDataURL('image/png');
    const prompt = document.getElementById('prompt').value;

    CentralSpaceShowProcessing(0, defaultLoadingMessage);

    const formData = new URLSearchParams({
        ...csrf_pair,
        mask: mask,
        imageType: document.getElementById('downloadType').value,
        mode: document.getElementById('editMode').value,
        prompt: prompt,
        ajax: true
    });

    try {
        const response = await fetch(submit_url, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.image_base64) {

            // Store full-res image
            outputImageDataURL = `data:image/png;base64,${result.image_base64}`;

            const resultImg = new Image();

            resultImg.onload = () => {
                // Keep display canvas size fixed
                canvas.width = displayWidth;
                canvas.height = displayHeight;

                overlayCanvas.width = displayWidth;
                overlayCanvas.height = displayHeight;

                resetCanvasContext();
                ctx.clearRect(0, 0, displayWidth, displayHeight);
                ctx.drawImage(resultImg, 0, 0, displayWidth, displayHeight);

                document.getElementById('downloadOptions').style.visibility = 'visible';
                CentralSpaceHideProcessing();
            };

            resultImg.src = outputImageDataURL;

        } else {
            console.error('No valid image returned', result);
            styledalert('OpenAI', result.error?.message || 'No image returned');
            CentralSpaceHideProcessing();
        }

    } catch (error) {
        console.error(error);
        styledalert('OpenAI', error.message || error);
        CentralSpaceHideProcessing();
    }
});

// Mode selector
document.getElementById('editMode').addEventListener('change', () => {
    const mode = document.getElementById('editMode').value;
    const brush = document.getElementById('penSize');
    const prompt = document.getElementById('prompt');

    brush.disabled = (mode === 'generate');
    prompt.disabled = (mode === 'white' || mode === 'black');
});

// Download / save
document.getElementById('downloadBtn').addEventListener('click', () => {

    // Use high-res image if available
    const dataURL = outputImageDataURL || canvas.toDataURL(document.getElementById('downloadType').value);
    const downloadAction = document.getElementById('downloadAction').value;

    if (downloadAction === 'download') {

        const link = document.createElement('a');
        link.href = dataURL;
        link.download = 'ai_edited.' + document.getElementById('downloadType').value.split('/')[1];
        link.click();
    }

    if (downloadAction === 'alternative' || downloadAction === 'new') {

        CentralSpaceShowProcessing(0);

        const formData = new URLSearchParams({
            ...csrf_pair,
            ajax: true,
            imageData: dataURL,
            imageType: document.getElementById('downloadType').value
        });

        fetch(downloadAction === 'alternative' ? alternative_url : save_new_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (downloadAction === 'alternative') {
                window.location.href = view_url;
            } else {
                window.location.href = view_new_url + result['resource'];
            }
        })
        .catch(error => {
            alert('Error submitting image:' + error);
        });
    }
});