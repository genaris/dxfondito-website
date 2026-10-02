#!/usr/bin/env bash
# Uploads the dist/ folder to the host through FTP.
# The GitHub workflow and the developer use this script.
#
# Necessary tools: lftp, and sha256sum or shasum.
# Necessary variables: FTP_SERVER, FTP_USERNAME, FTP_PASSWORD.
# Optional variables: FTP_PROTOCOL (ftps or ftp, default: ftps),
#   FTP_PUBLIC_DIR (default: public_html), FTP_PRIVATE_DIR (default: dxfondito-app),
#   FTP_FULL_UPLOAD (true: upload all files, for example after a manual change on the host),
#   DRY_RUN (true: show the files to upload, and upload nothing).
#
# The script uploads only the changed files. The private folder on the host keeps a manifest with the
# SHA-256 of each uploaded file (.deploy-manifest). A file with the same SHA-256 stays.
# The modification times cannot tell this: the workflow makes all files again for each installation.
#
# The script does not delete files on the host. Thus config.php and storage/ stay.
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
: "${FTP_SERVER:?Set FTP_SERVER}"
: "${FTP_USERNAME:?Set FTP_USERNAME}"
: "${FTP_PASSWORD:?Set FTP_PASSWORD}"
protocol="${FTP_PROTOCOL:-ftps}"
public_dir="${FTP_PUBLIC_DIR:-public_html}"
public_dir="${public_dir%/}"
private_dir="${FTP_PRIVATE_DIR:-dxfondito-app}"
private_dir="${private_dir%/}"
manifest_name=".deploy-manifest"

case "$protocol" in
    ftps) ssl_force=true ;;
    ftp) ssl_force=false ;;
    *) echo "FTP_PROTOCOL must be ftps or ftp." >&2; exit 1 ;;
esac

if [[ ! -d "$root/dist/public" || ! -d "$root/dist/private" ]]; then
    echo "The dist/ folder is absent. Run tools/build.sh first." >&2
    exit 1
fi

if command -v sha256sum >/dev/null 2>&1; then
    checksum() { sha256sum "$@"; }
else
    checksum() { shasum -a 256 "$@"; }
fi

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

# ftps is explicit TLS on port 21. lftp also encrypts the data connections.
ftp() {
    LFTP_PASSWORD="$FTP_PASSWORD" lftp --env-password -u "$FTP_USERNAME" "$FTP_SERVER" <<LFTP
set cmd:fail-exit yes
set ftp:ssl-force $ssl_force
set ftp:ssl-protect-data $ssl_force
set net:max-retries 3
set net:timeout 30
set xfer:clobber on
set xfer:make-backup off
$1
LFTP
}

# The manifest: one line for each file, "<sha-256>  <public|private>/<path>".
(cd "$root/dist" && find public private -type f | LC_ALL=C sort | while IFS= read -r file; do checksum "$file"; done) \
    | sed 's/^\([0-9a-f]*\) [ *]*/\1  /' > "$work/new"

: > "$work/old"
if [[ "${FTP_FULL_UPLOAD:-false}" != "true" ]]; then
    # The first installation has no manifest. Then all files go up.
    ftp "get \"$private_dir/$manifest_name\" -o \"$work/old\"" >/dev/null 2>&1 || : > "$work/old"
fi

# The changed and the new files.
grep -v -x -F -f "$work/old" "$work/new" | sed 's/^[0-9a-f]*  //' > "$work/changed" || true

if [[ ! -s "$work/changed" ]]; then
    echo "No changed files."
    exit 0
fi

# The files that join the others go last: the classes, the libraries and the pages that they use are then
# already on the host. The private folder goes before the public folder.
order() {
    case "$1" in
        private/src/App.php|private/vendor/autoload.php|private/vendor/composer/*) echo "2 $1" ;;
        private/*) echo "1 $1" ;;
        public/api/*|public/index.html) echo "4 $1" ;;
        *) echo "3 $1" ;;
    esac
}
while IFS= read -r file; do order "$file"; done < "$work/changed" | sort -s -k1,1n | cut -d' ' -f2- > "$work/ordered"

echo "$(wc -l < "$work/ordered" | tr -d ' ') changed files of $(wc -l < "$work/new" | tr -d ' '):"
sed 's/^/  /' "$work/ordered"

if [[ "${DRY_RUN:-false}" == "true" ]]; then
    exit 0
fi

# The lftp commands: one folder creation for each new folder, one "put" for each file, and the manifest last.
# A put replaces the file on the host. It does not delete it first.
{
    remote() {
        case "$1" in
            public/*) echo "$public_dir/${1#public/}" ;;
            private/*) echo "$private_dir/${1#private/}" ;;
        esac
    }
    # The folders first. Bash 3 of macOS has no associative arrays: sort -u removes the repeated folders.
    while IFS= read -r file; do dirname "$(remote "$file")"; done < "$work/ordered" | sort -u \
        | while IFS= read -r folder; do echo "mkdir -p -f \"$folder\""; done
    while IFS= read -r file; do
        echo "put \"$root/dist/$file\" -o \"$(remote "$file")\""
    done < "$work/ordered"
    echo "put \"$work/new\" -o \"$private_dir/$manifest_name\""
} > "$work/commands"

ftp "$(cat "$work/commands")"
echo "Done."
