#!/usr/bin/env bash
# Uploads the dist/ folder to the host through FTP.
# The GitHub workflow and the developer use this script.
#
# Necessary tool: lftp.
# Necessary variables: FTP_SERVER, FTP_USERNAME, FTP_PASSWORD.
# Optional variables: FTP_PROTOCOL (ftps or ftp, default: ftps),
#   FTP_PUBLIC_DIR (default: public_html), FTP_PRIVATE_DIR (default: dxfondito-app).
#
# The script does not delete files on the host.
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
: "${FTP_SERVER:?Set FTP_SERVER}"
: "${FTP_USERNAME:?Set FTP_USERNAME}"
: "${FTP_PASSWORD:?Set FTP_PASSWORD}"
protocol="${FTP_PROTOCOL:-ftps}"
# Without the final "/". With it, lftp puts dist/public in a "public" subfolder of the target.
public_dir="${FTP_PUBLIC_DIR:-public_html}"
public_dir="${public_dir%/}"
private_dir="${FTP_PRIVATE_DIR:-dxfondito-app}"
private_dir="${private_dir%/}"

case "$protocol" in
    ftps) ssl_force=true ;;
    ftp) ssl_force=false ;;
    *) echo "FTP_PROTOCOL must be ftps or ftp." >&2; exit 1 ;;
esac

if [[ ! -d "$root/dist/public" || ! -d "$root/dist/private" ]]; then
    echo "The dist/ folder is absent. Run tools/build.sh first." >&2
    exit 1
fi

# ftps is explicit TLS on port 21. lftp also encrypts the data connections.
# The private folder goes first. Thus the public entry file always finds its source files.
LFTP_PASSWORD="$FTP_PASSWORD" lftp --env-password -u "$FTP_USERNAME" "$FTP_SERVER" <<LFTP
set cmd:fail-exit yes
set ftp:ssl-force $ssl_force
set ftp:ssl-protect-data $ssl_force
set net:max-retries 3
set net:timeout 30
mirror --reverse --verbose "$root/dist/private" "$private_dir"
mirror --reverse --verbose "$root/dist/public" "$public_dir"
LFTP
