#!/usr/bin/env bash
set -euo pipefail

NO_SETUP=false
NO_TEARDOWN=false
PEST_ARGS=()
PEST_ENV=()

while [[ $# -gt 0 ]]; do
    case "$1" in
        # Use --env NAME=value to set environment variables for Pest, e.g. --env DISPLAY=:99 --debug. Repeatable option.
        --env)
            if [[ $# -lt 2 || ! "$2" =~ ^[a-zA-Z_][a-zA-Z0-9_]*= ]]; then
                echo "--env requires an environment assignment in NAME=value format." >&2
                exit 2
            fi
            PEST_ENV+=("$2")
            shift 2
            ;;
        --nosetup)
            NO_SETUP=true
            shift
            ;;
        --noteardown)
            NO_TEARDOWN=true
            shift
            ;;
        --)
            shift
            PEST_ARGS+=("$@")
            break
            ;;
        *)
            PEST_ARGS+=("$1")
            shift
            ;;
    esac
done

# Validate before provisioning, including arguments following the optional separator.
for argument in "${PEST_ARGS[@]}"; do
    case "$argument" in
        --parallel|--parallel=*|-p*|--processes|--processes=*)
            echo "Parallel browser tests are not supported: the test environment is shared." >&2
            exit 2
            ;;
    esac
done

cleanup() {
    exit_code=$?

    if [ -n "${SERVER_PID:-}" ]; then
        kill "$SERVER_PID" 2>/dev/null || true
    fi

    if [ "$NO_TEARDOWN" = false ]; then
        if [ "$exit_code" -eq 0 ]; then
            composer test:env:cleanup
        else
            echo "Tests failed! Preserving the test environment for debugging purposes"
        fi
    else
        echo "Skipping test environment cleanup (--noteardown)"
    fi

    exit "$exit_code"
}

trap cleanup EXIT

mkdir -p tests/.runtime

if [ "$NO_SETUP" = false ]; then
    composer test:env:provision
else
    echo "Skipping test environment provisioning (--nosetup)"
fi

export RS_TEST_MODE=1
SERVER_LOG=tests/.runtime/php-server.log
SERVER_TOKEN=$(php -r 'echo bin2hex(random_bytes(32));')
# Tests rewrite config.test.php between cases; the server should always execute the current file contents (no caching)
RS_BROWSER_SERVER_TOKEN="$SERVER_TOKEN" php -d opcache.enable=0 -S 127.0.0.1:8081 -t . tests/Support/browser-server-router.php > "$SERVER_LOG" 2>&1 &

SERVER_PID=$!

startup_failure() {
    echo "PHP test server startup failed: $1" >&2
    echo "Server log: $SERVER_LOG" >&2
    tail -n 30 "$SERVER_LOG" >&2
    exit 1
}

echo "Waiting for the PHP test server on port 8081 (log file $SERVER_LOG)"
startup_deadline=$((SECONDS + 10))
while true; do
    if ! kill -0 "$SERVER_PID" 2>/dev/null; then
        startup_failure "server exited; check the log for a port conflict or startup error"
    fi

    if php tests/Support/check-browser-server.php "$SERVER_TOKEN"; then
        if ! kill -0 "$SERVER_PID" 2>/dev/null; then
            startup_failure "server exited during the readiness check"
        fi
        break
    fi

    if [ "$SECONDS" -ge "$startup_deadline" ]; then
        startup_failure "readiness was not confirmed within 10 seconds"
    fi
    sleep 0.1
done

env "${PEST_ENV[@]}" php -d auto_prepend_file=tests/Support/bootstrap-resourcespace-test-env.php vendor/bin/pest --testsuite=Browser "${PEST_ARGS[@]}"
