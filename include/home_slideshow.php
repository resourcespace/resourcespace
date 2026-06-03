<script>

(function () {
    <?php
    $slideshow_img_urls = array_column(array_filter(get_slideshow_files_data(), static fn ($V): bool => (bool) $V['homepage_show'] === true), 'file_url');
    $slideshow_configured = $slideshow_img_urls !== [];
    ?>

    const images = <?php echo encode_js_value($slideshow_img_urls); ?>;

    jQuery(document).ready(function() {

    const container = document.getElementById("hero_banner");
        // No login page slideshow images configured
        if (images.length === 0) {
            container.style.backgroundColor= `var(--colour-brand-primary-default)`;
            return;
        }

        <?php
        if ($static_slideshow_image) {
            # Leave only 1 random image to be displayed.
            ?>
            images.splice(0, images.length, images[Math.floor(Math.random() * images.length)]);
            <?php
        }
        ?>

        // Just one slideshow image configured
        if (images.length === 1) {
            const div = document.createElement("div");
            div.className = "hero-slide active";
            div.style.backgroundImage = `url(${images[0]})`;
            container.appendChild(div);
            return;
        }

        // Multiple slideshow images configured
        const slides = images.map((url, i) => {
            const div = document.createElement("div");
            div.className = "hero-slide" + (i === 0 ? " active" : "");
            div.style.backgroundImage = `url(${url})`;
            container.appendChild(div);
            return div;
        })

        let index = 0;

        setInterval(() => {
            slides[index].classList.remove("active");
            index = (index + 1) % slides.length;
            slides[index].classList.add("active");
        }, <?php echo (int) $slideshow_photo_delay * 1000; ?>);

        const div = document.createElement("div");
        div.className = "hero-banner-progress";
        div.innerHTML = `<svg viewBox="0 0 100 100">
            <circle class="track" cx="50" cy="50" r="45"></circle>
            <circle class="progress" cx="50" cy="50" r="45"></circle>
            </svg>`;
        container.appendChild(div);
        return div;
    });
})();

</script>