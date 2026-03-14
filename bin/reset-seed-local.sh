#!/usr/bin/env bash
set -euo pipefail

APP_ENV_VALUE="${APP_ENV:-dev}"
CONSOLE_BIN="${CONSOLE_BIN:-php bin/console}"

printf '\n[recruit] reset + seed (%s)\n' "${APP_ENV_VALUE}"

${CONSOLE_BIN} doctrine:database:drop --if-exists --force --env="${APP_ENV_VALUE}"
${CONSOLE_BIN} doctrine:database:create --env="${APP_ENV_VALUE}"
${CONSOLE_BIN} doctrine:schema:update --force --complete --env="${APP_ENV_VALUE}"
${CONSOLE_BIN} doctrine:fixtures:load -n --env="${APP_ENV_VALUE}"

printf '\n[recruit] done\n'
