
const canvas = document.getElementById('canvas');
const ctx = canvas.getContext('2d');

const overlayCanvas = document.getElementById('overlayCanvas');
const overlayCtx = overlayCanvas.getContext('2d');

const image = document.getElementById('image');

let drawing = false;
let penSize = document.getElementById('penSize').value;
let lastX = 0;
let lastY = 0;

image.onload = drawImageOnCanvas;

function drawImageOnCanvas() {
    canvas.width = image.width
    canvas.height = image.height;

    overlayCanvas.width = image.width
    overlayCanvas.height = image.height;

    ctx.drawImage(image, 0, 0, image.width, image.height);
    
    document.getElementById('canvas-container').style.visibility='visible';
    document.getElementById('toolbox').style.visibility='visible';
    CentralSpaceHideProcessing();
    HideThumbs(); // More visual space
};

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

    if (document.getElementById('penSize').disabled) {return false;}

    const [x, y] = getMousePos(e);
    const mode = document.getElementById('editMode').value;

    e.preventDefault(); 
    
    // Clear the previous brush preview
    overlayCtx.clearRect(0, 0, overlayCanvas.width, overlayCanvas.height);

    if (!drawing) 
        {
        // Not drawing, draw a pen preview only.
        // Draw a circle at the current mouse position

        // Set dashed pattern for visibility on any background
        overlayCtx.setLineDash([5, 5]);  // Alternating dash pattern
        overlayCtx.lineWidth = 2;

        // Draw a white circle outline
        overlayCtx.strokeStyle = 'white';
        overlayCtx.beginPath();
        overlayCtx.arc(x, y, penSize / 2, 0, Math.PI * 2);
        overlayCtx.stroke();

        // Draw a black circle outline over the white to create contrast
        overlayCtx.strokeStyle = 'black';
        overlayCtx.lineWidth = 1;
        overlayCtx.beginPath();
        overlayCtx.arc(x, y, penSize / 2, 0, Math.PI * 2);
        overlayCtx.stroke();
        return;
        }
        
    ctx.lineWidth = penSize;
    ctx.globalCompositeOperation = 'destination-out';
    ctx.strokeStyle = "black";
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    if (mode=="clone" || mode=="white" || mode=="black")
        {
        // Add shadow for blur effect
        ctx.lineWidth = penSize/3;
        ctx.shadowColor = 'black'; // Set shadow color to match stroke
        ctx.shadowBlur = penSize/2;       // Increase this value for stronger blur
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
        e.clientX - rect.left,
        e.clientY - rect.top
    ];
}

// Hide the brush preview when the mouse leaves the main canvas
canvas.addEventListener('mouseleave', () => {
    overlayCtx.clearRect(0, 0, overlayCanvas.width, overlayCanvas.height);
    drawing=false;
});

// Submit canvas as mask
document.getElementById('submitBtn').addEventListener('click', async () => {

   
    const tempCanvas = document.createElement('canvas');
    const tempCtx = tempCanvas.getContext('2d');
    tempCanvas.width = 1024;
    tempCanvas.height = 1024;
    tempCtx.drawImage(canvas, 0, 0);

    const mask = tempCanvas.toDataURL('image/png');
    const prompt = document.getElementById('prompt').value;

    CentralSpaceShowProcessing(0,defaultLoadingMessage);

    // Send mask and image to the backend via AJAX

    // Prepare form data
    const formData = new URLSearchParams({
        ...csrf_pair,
        mask: mask,
        imageType: document.getElementById('downloadType').value,
        mode: document.getElementById('editMode').value,
        prompt: prompt,
        ajax: true
    });

    const response = await fetch(submit_url, {
        method: 'POST',
        body: formData
    });
    
    const result = await response.json();  // Parse the response as JSON

    // Check if a valid image URL is returned
    if (result.image_base64) {

        const base64Image = result.image_base64;  // Extract the base64 image data

        // Update the image src behind the canvas with the new one
        image.src = `data:image/png;base64,${base64Image}`;

        // Redraw the canvas with the new image once it loads
        image.onload = () => {

        // I have no idea why this is necessary or why it does anything, but without it the following drawImage() fails to draw anything.
        canvas.width = canvas.width;
        canvas.height = canvas.height;

        ctx.drawImage(image, 0, 0);  // Draw new image

        document.getElementById('downloadOptions').style.visibility='visible';
        };
    } else {
        console.error('Failed to get a valid image URL from OpenAI.');
        styledalert('OpenAI',result.error.message);
    }
    CentralSpaceHideProcessing();
});

// Get the mode selector and add an event listener
document.getElementById('editMode').addEventListener('change', () => {
    const mode = document.getElementById('editMode').value;
    const brush = document.getElementById('penSize');
    const prompt = document.getElementById('prompt');
    brush.disabled=(mode=='generate' || mode=='variation');
    prompt.disabled=(mode=='white' || mode=='black' || mode=='variation');
});


// Get the download button and add an event listener
document.getElementById('downloadBtn').addEventListener('click', () => {
    // Convert the canvas content to a data URL (PNG format)
    const dataURL = canvas.toDataURL(document.getElementById('downloadType').value);
    const downloadAction=document.getElementById('downloadAction').value;

    if (downloadAction=='download')
        {
        // Download the file (or really, save, as it's already on the user's system)

        // Create a temporary link element
        const link = document.createElement('a');
        link.href = dataURL;
        link.download = 'ai_edited.' + document.getElementById('downloadType').value.split('/')[1];  // The name of the downloaded image

        // Programmatically click the link to trigger the download
        link.click();
        }

    if (downloadAction=='alternative' || downloadAction=='new')
        {
        // Save as alternative file
        CentralSpaceShowProcessing(0);

        // Prepare form data
        const formData = new URLSearchParams({
            ...csrf_pair,
            ajax: true,
            imageData: dataURL,
            imageType: document.getElementById('downloadType').value
        });

        fetch(downloadAction=='alternative' ? alternative_url : save_new_url, {
            method: 'POST',
            body: formData
            })
            .then(response => response.json())
            .then(result => {
                console.log('Image submitted successfully:', result);

                if (downloadAction=='alternative')
                    {
                    window.location.href=view_url;
                    }
                else    
                    {
                    window.location.href=view_new_url + result['resource'];
                    }
            })
            .catch(error => {
                alert('Error submitting image:' + error);
            });
        }

        
});
