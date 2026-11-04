(function () {
    'use strict';

    // ResourceSpace loads view.php repeatedly through AJAX and in modals.
    if (window.videoSceneClickHandler) {
        return;
    }
    window.videoSceneClickHandler = true;

    document.addEventListener('submit', async function (event) {
        const form = event.target.closest('.video-scene-description-form');
        if (!form) {
            return;
        }
        event.preventDefault();
        if (form.dataset.saving === 'true') {
            return;
        }
        const panel = form.closest('.video-scene-panel');
        const cell = form.closest('.video-scene-description');
        const textarea = form.elements.description;
        const error = form.querySelector('.video-scene-edit-error');
        const data = new FormData(form);
        form.dataset.saving = 'true';
        error.hidden = true;
        form.querySelectorAll('button, textarea').forEach(element => { element.disabled = true; });
        try {
            const response = await fetch(form.action, {method: 'POST', body: data, credentials: 'same-origin'});
            const result = await response.json();
            if (!response.ok || typeof result.description !== 'string') {
                throw new Error(typeof result.error === 'string' ? result.error : panel.dataset.saveFailed);
            }
            cell.querySelector('.video-scene-description-text').textContent = result.description
                || panel.dataset.emptyDescription;
            textarea.value = result.description;
            textarea.defaultValue = result.description;
            form.hidden = true;
            cell.querySelector('.video-scene-description-display').hidden = false;
            const message = panel.querySelector('.video-scene-message');
            message.textContent = panel.dataset.saved;
            message.hidden = false;
            cell.querySelector('.video-scene-edit').focus();
        } catch (failure) {
            error.textContent = failure.message || panel.dataset.saveFailed;
            error.hidden = false;
        } finally {
            form.dataset.saving = 'false';
            form.querySelectorAll('button, textarea').forEach(element => { element.disabled = false; });
        }
    });

    document.addEventListener('click', function (event) {
        const edit = event.target.closest('.video-scene-edit, .video-scene-edit-cancel');
        if (edit) {
            const cell = edit.closest('.video-scene-description');
            const form = cell.querySelector('.video-scene-description-form');
            const opening = edit.matches('.video-scene-edit');
            form.reset();
            form.querySelector('.video-scene-edit-error').hidden = true;
            form.hidden = !opening;
            cell.querySelector('.video-scene-description-display').hidden = opening;
            (opening ? form.elements.description : cell.querySelector('.video-scene-edit')).focus();
            return;
        }
        const button = event.target.closest('.video-scene-play');
        if (!button) {
            return;
        }
        const panel = button.closest('.video-scene-panel');
        const playerElement = document.getElementById(panel.dataset.playerId);
        const seconds = Number(button.dataset.start);
        const message = panel.querySelector('.video-scene-message');
        const player = playerElement && window.videojs && window.videojs.getPlayer(playerElement.id);
        const video = playerElement && (playerElement.matches('video')
            ? playerElement : playerElement.querySelector('video'));

        function unavailable() {
            message.textContent = panel.dataset.unavailable;
            message.hidden = false;
        }

        if (!Number.isFinite(seconds) || seconds < 0 || (!player && !video)) {
            unavailable();
            return;
        }

        function playScene() {
            const duration = player ? player.duration() : video.duration;
            if (!Number.isFinite(duration) || seconds >= duration) {
                unavailable();
                return;
            }
            message.hidden = true;
            if (player) {
                player.currentTime(seconds);
            } else {
                video.currentTime = seconds;
            }
            playerElement.scrollIntoView({behavior: 'smooth', block: 'center'});
            const focusElement = video || playerElement;
            focusElement.focus({preventScroll: true});
            const playing = player ? player.play() : video.play();
            if (playing && playing.catch) {
                playing.catch(function () {
                    // The native controls remain available if the browser requires another gesture.
                    focusElement.focus({preventScroll: true});
                });
            }
        }

        const readyState = player ? player.readyState() : video.readyState;
        if (readyState >= 1) {
            playScene();
        } else if (player) {
            player.one('loadedmetadata', playScene);
            player.load();
        } else {
            video.addEventListener('loadedmetadata', playScene, {once: true});
            video.load();
        }
    });
}());
