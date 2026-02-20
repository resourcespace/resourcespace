<script>
const images = [
    <?php 
    foreach (get_slideshow_files_data() as $slideshow_file_info) {
        if ((bool) $slideshow_file_info['login_show'] === false) {
            continue;
        }

        echo '"' . "{$baseurl_short}pages/download.php?slideshow=" . escape($slideshow_file_info['ref']) . '",';
    }
    ?>
];

jQuery(document).ready(function() {
    // No login page slideshow images configured
    if (images.length === 0) {
        return;
    }
    
    const container = document.getElementById("login-slideshow");

    // Just one slideshow image configured
    if (images.length === 1) {
        const div = document.createElement("div");
        div.className = "login-slide active";
        div.style.backgroundImage = `url(${images[0]})`;
        container.appendChild(div);
        return;
    }
    
    // Multiple slideshow images configured
    const slides = images.map((url, i) => {
        const div = document.createElement("div");
        div.className = "login-slide" + (i === 0 ? " active" : "");
        div.style.backgroundImage = `url(${url})`;
        container.appendChild(div);
        return div;
    });

    let index = 0;

    setInterval(() => {
        slides[index].classList.remove("active");
        index = (index + 1) % slides.length;
        slides[index].classList.add("active");
    }, <?php echo $slideshow_photo_delay * 1000; ?>);
});

</script>
