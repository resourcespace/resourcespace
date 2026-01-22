<?php
include __DIR__ . "/../../../include/boot.php";
include __DIR__ . "/../../../include/authenticate.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $clip_vector_url = $clip_service_url . "/vector";
    $clip_tag_url = $clip_service_url . "/tag";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $clip_vector_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Expect:']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'image' => new CURLFile($_FILES['image']['tmp_name'], $_FILES['image']['type'], $_FILES['image']['name'])
    ]);
    $vector_response = curl_exec($ch);
    curl_close($ch);

    $vector = json_decode($vector_response);

    // Get keywords
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $clip_tag_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Expect:']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'db' => $mysql_db,
        'vector' => json_encode($vector),
        'url' => $clip_keyword_url,
        'top_k' => 5
    ]);
    $tag_response = curl_exec($ch);
    curl_close($ch);
    $keywords = json_decode($tag_response);

    // Get single title
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $clip_tag_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Expect:']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'db' => $mysql_db,
        'vector' => json_encode($vector),
        'url' => $clip_title_url,
        'top_k' => 1
    ]);
    $title_response = curl_exec($ch);
    curl_close($ch);
    $titles = json_decode($title_response);

    header('Content-Type: application/json');
    echo json_encode([
        'keywords' => $keywords,
        'title' => isset($titles[0]->tag) ? urldecode($titles[0]->tag) : null
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Webcam Auto Tagger</title>
  <style>
    body
        {
        font-family: sans-serif;
        font-size: 1.2em;
        }
    video {
      display: block;
      width: 512px;
      height: auto;
      border: 1px solid #ccc;
      margin-bottom: 1em;
    }
    canvas { display: none; }
    #output {
      margin-top: 1em;
    }
    .pill {
      display: inline-block;
      background-color: #e0e0e0;
      color: #333;
      padding: 4px 10px;
      margin: 2px;
      border-radius: 15px;
    }
  </style>
</head>
<body>
  <h1>Webcam Tag Generator</h1>
  <p>Allow webcam access to start tagging...</p>
  <video id="video" autoplay playsinline></video>
  <canvas id="canvas" width="256" height="256"></canvas>
  <div id="output"></div>

  <script>
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const output = document.getElementById('output');
    const ctx = canvas.getContext('2d');
    const csrf = <?php echo generate_csrf_js_object('webcam_tag'); ?>;

    let processing = false;

    async function startWebcam() {
      try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: true });
        video.srcObject = stream;
        video.onloadedmetadata = () => {
          video.play();
          processLoop();
        };
      } catch (err) {
        output.innerText = 'Error accessing webcam: ' + err;
      }
    }

    function captureFrame() {
      const { videoWidth: w, videoHeight: h } = video;
      const size = Math.min(w, h);
      const sx = (w - size) / 2;
      const sy = (h - size) / 2;

      canvas.width = 256;
      canvas.height = 256;

      ctx.drawImage(video, sx, sy, size, size, 0, 0, 256, 256);
      return new Promise((resolve) => {
        canvas.toBlob(blob => resolve(blob), 'image/jpeg');
      });
    }

    async function processLoop() {
      if (processing) return;
      processing = true;

      const blob = await captureFrame();

      const formData = new FormData();
      formData.append('image', blob);
      for (const key in csrf) {
        formData.append(key, csrf[key]);
      }

      try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const result = await response.json();

        const title = result.title || '(no title)';
        const keywordHTML = Array.isArray(result.keywords)
          ? result.keywords.map(t => `<span class="pill">${t.tag}</span>`).join('')
          : '<em>No keywords</em>';

        output.innerHTML = `<strong>Title:</strong> ${title}<br><strong>Keywords:</strong><br>${keywordHTML}`;
      } catch (e) {
        console.error('Error:', e);
        output.innerText = 'Error: ' + e;
      }

      processing = false;
      requestAnimationFrame(processLoop);
    }

    startWebcam();
  </script>
</body>
</html>
