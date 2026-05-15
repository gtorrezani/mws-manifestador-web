#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

run_composer_install=false
if [[ ! -d vendor || ! -f vendor/autoload.php ]]; then
  run_composer_install=true
elif [[ composer.lock -nt vendor/autoload.php ]]; then
  run_composer_install=true
fi

if [[ "$run_composer_install" == true ]]; then
  composer install
else
  echo "composer install skipped: vendor is present and current."
fi

run_npm_ci=false
if [[ ! -d node_modules ]]; then
  run_npm_ci=true
elif [[ package-lock.json -nt node_modules/.package-lock.json ]]; then
  run_npm_ci=true
fi

if [[ "$run_npm_ci" == true ]]; then
  npm ci
else
  echo "npm ci skipped: node_modules is present and current."
fi

composer quality
npm run quality
