#!/usr/bin/env bash
# Uploads the dist/ folder to the host through FTP.
# Use this script only when the GitHub workflow is not available.
#
# Necessary tool: lftp.
# Necessary variables: FTP_SERVER, FTP_USERNAME, FTP_PASSWORD.
# Optional variables: FTP_PUBLIC_DIR (default: public_html), FTP_PRIVATE_DIR (default: dxfondito-app).
#
# The script does not delete files on the host.
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
: "${FTP_SERVER:?Set FTP_SERVER}"
: "${FTP_USERNAME:?Set FTP_USERNAME}"
: "${FTP_PASSWORD:?Set FTP_PASSWORD}"
public_dir="${FTP_PUBLIC_DIR:-public_html}"
private_dir="${FTP_PRIVATE_DIR:-dxfondito-app}"

if [[ ! -d "$root/dist/public" || ! -d "$root/dist/private" ]]; then
    echo "The dist/ folder is absent. Run tools/build.sh first." >&2
    exit 1
fi

LFTP_PASSWORD="$FTP_PASSWORD" lftp --env-password -u "$FTP_USERNAME" "$FTP_SERVER" <<LFTP
set cmd:fail-exit yes
mirror --reverse --verbose "$root/dist/private" "$private_dir"
mirror --reverse --verbose "$root/dist/public" "$public_dir"
LFTP
