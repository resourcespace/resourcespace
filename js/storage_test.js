/** Initialise an Installation Check panel, including when loaded through CentralSpace or a modal. */
function storageTestInit(panel, options) {
    if (!panel || !panel.isConnected) {
        return;
    }
    const text = options.strings;
    const find = function (name) { return panel.querySelector('#storage-test-' + name); };
    const start = panel.parentElement.querySelector('#storage-test-start');
    let active = false;
    let cancelling = false;
    let current = null;
    let retries = 0;
    let waitingTimer = null;
    let testStarted = 0;
    let requested = null;

    function stopWaiting() {
        clearInterval(waitingTimer);
        waitingTimer = null;
    }

    function abandoned() {
        if (!active || !current || current.recovering) {
            return;
        }
        const data = new FormData();
        Object.entries(Object.assign({ajax: true, action: 'cancel', id: current.id, sequence: current.sequence}, options.token))
            .forEach(function (entry) { data.append(entry[0], entry[1]); });
        if (navigator.sendBeacon) {
            navigator.sendBeacon(options.url, data);
        }
    }

    function finish() {
        active = false;
        stopWaiting();
        find('cancel').hidden = true;
        start.removeAttribute('aria-disabled');
        window.removeEventListener('pagehide', abandoned);
    }

    function results(state) {
        find('metrics').replaceChildren();
        const lines = [text.storage_test_title, find('status').textContent,
            text.storage_test_written + ': ' + state.written,
            text.storage_test_read_bytes + ': ' + state.read];
        Object.entries(state.metrics).forEach(function (entry) {
            const label = text['storage_test_metric_' + entry[0]];
            const value = entry[1].value + ' ' + entry[1].unit;
            const row = document.createElement('tr');
            [label, value].forEach(function (content) {
                const cell = document.createElement('td');
                cell.textContent = content;
                row.appendChild(cell);
            });
            find('metrics').appendChild(row);
            lines.push(label + ': ' + value);
        });
        if (state.limited || state.outcome === 'limited') {
            lines.push(text.storage_test_limited);
        }
        if (state.slow) {
            lines.push(text.storage_test_slow);
        }
        lines.push(text.storage_test_workload, text.storage_test_method);
        find('summary').value = lines.join('\n');
        find('results').hidden = false;
    }

    function render(state) {
        find('operations').textContent = Number(state.operations || 0).toLocaleString();
        find('operations-total').textContent = Number(state.operations_total || 0).toLocaleString();
        find('written').textContent = Number(state.written || 0).toLocaleString();
        find('read').textContent = Number(state.read || 0).toLocaleString();
        find('operation').textContent = text['storage_test_' + state.stage]
            + (state.stage === 'done' ? '' : ' (' + state.completed + ' / ' + state.total + ')')
            + (state.item ? ': ' + state.item : '');
        if (state.last_operation) {
            const last = state.last_operation;
            find('last-operation').textContent = text['storage_test_last_' + last.kind] + ': ' + last.path
                + (last.bytes ? ' (' + last.bytes.toLocaleString() + ' ' + text.storage_test_bytes + ')' : '');
        }
        find('progress').value = state.progress;
        find('percent').textContent = state.progress + '%';
        const warnings = [];
        if (state.limited || state.outcome === 'limited') {
            warnings.push(text.storage_test_limited);
        }
        if (state.slow) {
            warnings.push(text.storage_test_slow);
        }
        if (state.error) {
            warnings.push(text[state.error] || text.storage_test_io_error);
        }
        find('warning').textContent = warnings.join(' ');
        find('warning').hidden = warnings.length === 0;
        if (state.recovering) {
            find('status').textContent = text.storage_test_recovering;
        } else if (state.stage === 'done') {
            find('status').textContent = state.outcome === 'cancelled' ? text.storage_test_cancelled
                : (state.outcome === 'error' ? text.storage_test_failed : text.storage_test_done);
            results(state);
        } else {
            find('status').textContent = text.storage_test_running;
        }
    }

    function request(action) {
        if (!panel.isConnected && current && current.recovering) {
            cancelling = true;
        } else if (!panel.isConnected && action !== 'start') {
            cancelling = true;
            action = 'cancel';
        }
        requested = performance.now();
        jQuery.ajax({
            url: options.url,
            method: 'POST',
            dataType: 'json',
            timeout: 20000,
            data: Object.assign({ajax: true, action: action, id: current ? current.id : '',
                sequence: current ? current.sequence : 0}, options.token)
        }).done(function (state) {
            requested = null;
            find('waiting').textContent = '';
            retries = 0;
            current = state;
            try {
                render(state);
            } catch (error) {
                find('status').textContent = text.storage_test_display_error;
                abandoned();
                finish();
                return;
            }
            if (state.error === 'storage_test_cleanup_error') {
                find('status').textContent = text.storage_test_cleanup_error;
                results(state);
                finish();
            } else if (state.recovering && cancelling && state.stage === 'done') {
                finish();
            } else if (state.recovering) {
                setTimeout(function () { request('start'); }, 50);
            } else if (state.stage === 'done') {
                finish();
            } else {
                setTimeout(function () { request(cancelling ? 'cancel' : 'step'); }, 50);
            }
        }).fail(function (xhr) {
            requested = null;
            // Repeating a step with the same sequence is safe even if its response was lost.
            if (current && (xhr.status === 0 || xhr.status === 409) && ++retries <= 3) {
                setTimeout(function () { request(cancelling ? 'cancel' : action); }, 500);
                return;
            }
            const error = xhr.responseJSON && xhr.responseJSON.error;
            find('status').textContent = typeof error === 'string' ? error : text.storage_test_connection;
            abandoned();
            finish();
        });
    }

    start.addEventListener('click', function (event) {
        event.preventDefault();
        if (active) {
            return;
        }
        active = true;
        testStarted = performance.now();
        find('elapsed').textContent = '0';
        stopWaiting();
        waitingTimer = setInterval(function () {
            find('elapsed').textContent = Math.floor((performance.now() - testStarted) / 1000).toString();
            const seconds = requested === null ? 0 : Math.floor((performance.now() - requested) / 1000);
            find('waiting').textContent = seconds > 0
                ? text.storage_test_waiting.replace('%seconds', seconds.toString()) : '';
        }, 2000);
        find('waiting').textContent = '';
        cancelling = false;
        retries = 0;
        current = null;
        panel.hidden = false;
        find('results').hidden = true;
        find('warning').hidden = true;
        find('cancel').hidden = false;
        find('cancel').disabled = false;
        find('operations').textContent = '0';
        find('operations-total').textContent = '0';
        find('written').textContent = '0';
        find('read').textContent = '0';
        find('last-operation').textContent = '';
        find('progress').value = 0;
        find('percent').textContent = '0%';
        find('status').textContent = text.storage_test_running;
        start.setAttribute('aria-disabled', 'true');
        window.addEventListener('pagehide', abandoned);
        request('start');
    });
    find('cancel').addEventListener('click', function () {
        cancelling = true;
        find('cancel').disabled = true;
        find('status').textContent = text.storage_test_cleanup;
    });
    find('copy').addEventListener('click', function () {
        const fallback = function () {
            find('summary').focus();
            find('summary').select();
            find('copy-status').textContent = text.storage_test_select_copy;
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(find('summary').value).then(function () {
                find('copy-status').textContent = text.storage_test_copied;
            }, fallback);
        } else {
            fallback();
        }
    });
}
