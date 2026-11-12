// Keep real sources out of the preload scanner; request previews only inside the viewport.
(() => {
    const selector = 'img[data-preview-src]';
    const load = (image) => {
        image.addEventListener('load', () => {
            if (image.naturalWidth > 0) {
                image.style.removeProperty('opacity');
            }
        }, { once: true });
        image.addEventListener('error', () => {
            image.style.opacity = '0';
        });
        image.loading = 'eager';
        image.src = image.dataset.previewSrc;
        image.removeAttribute('data-preview-src');
    };
    const observer = typeof IntersectionObserver === 'function'
        ? new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    observer.unobserve(entry.target);
                    load(entry.target);
                }
            }
        }, { rootMargin: '0px' })
        : null;
    const scan = (root, removed = false) => {
        if (root.nodeType !== Node.ELEMENT_NODE) {
            return;
        }
        const watch = (image) => {
            if (removed) {
                observer?.unobserve(image);
                return;
            }
            image.style.removeProperty('display');
            if (observer) {
                observer.observe(image);
            } else {
                load(image);
            }
        };
        if (root.matches(selector)) {
            watch(root);
        }
        root.querySelectorAll(selector).forEach(watch);
    };
    scan(document.documentElement);
    // Includes streamed HTML, AJAX results, modal content and collection-bar refreshes.
    new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            mutation.removedNodes.forEach((root) => scan(root, true));
            mutation.addedNodes.forEach((root) => scan(root));
        }
    }).observe(document.documentElement, { childList: true, subtree: true });
})();
