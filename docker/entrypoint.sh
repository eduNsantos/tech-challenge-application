#!/bin/sh
set -eu

if [ -n "${NEW_RELIC_API_KEY:-}" ] && [ -n "${NEW_RELIC_ACCOUNT_ID:-}" ]; then
    /usr/local/bin/newrelic install -n logs-integration
fi

exec "$@"
