#!/usr/bin/env bash
# Makes the files for the host in the dist/ folder.
#
#   dist/public/    Put the contents in the public folder of the host.
#   dist/private/   Put the contents in the private folder of the host.
#
# PRIVATE_PATH gives the location of the private folder on the host.
# It is a path from the api/ folder in the public folder, or an absolute path.
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
dist="$root/dist"
private_path="${PRIVATE_PATH:-../../dxfondito-app}"

rm -rf "$dist"
mkdir -p "$dist/public/api" "$dist/private"

echo "== Browser program"
(cd "$root/web" && pnpm install --frozen-lockfile && pnpm build)
cp -R "$root/web/dist/." "$dist/public/"

echo "== API entry file"
cp "$root/api/public/index.php" "$dist/public/api/index.php"
if [[ "$private_path" == /* ]]; then
    printf "<?php\n\nreturn '%s';\n" "$private_path" > "$dist/public/api/private-path.php"
else
    printf "<?php\n\nreturn __DIR__ . '/%s';\n" "$private_path" > "$dist/public/api/private-path.php"
fi

# PHP-FPM reads .user.ini in the folder of the script. The API accepts ADIF files up to 5 MB.
cp "$root/docker/php/uploads.ini" "$dist/public/api/.user.ini"

echo "== API source files and libraries"
cp -R "$root/api/src" "$root/api/migrations" "$dist/private/"
cp "$root/api/composer.json" "$root/api/composer.lock" "$root/api/config.example.php" "$dist/private/"

composer_args=(install --no-dev --optimize-autoloader --no-interaction --no-progress)
if command -v composer >/dev/null 2>&1; then
    composer "${composer_args[@]}" --working-dir "$dist/private"
else
    # Without a local Composer, the PHP container of the local environment does this step.
    docker compose -f "$root/compose.yaml" run --rm --no-deps -v "$dist/private:/build" api \
        composer "${composer_args[@]}" --working-dir /build
fi
rm "$dist/private/composer.json" "$dist/private/composer.lock"

echo "== Done: $dist"
