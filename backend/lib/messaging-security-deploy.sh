#!/usr/bin/env bash
# Run on CT 7510. Default: review only. Does not delete media or restart production.
set -euo pipefail
security_beta=/var/www/dnct.pl/public_html/WEB/beta.dnct.pl
security_patch="$security_beta/backend/lib/admin-security.patch"
security_mode=--check
if [ "$#" -gt 0 ]; then security_mode="$1"; fi
case "$security_mode" in --check|--apply) ;; *) echo 'Use --check or --apply' >&2; exit 2;; esac
node --check "$security_beta/backend/lib/chat-realtime-server.mjs"
node --check "$security_beta/backend/lib/messaging-security.mjs"
patch --dry-run --batch --forward -d / -p1 < "$security_patch"
bash "$security_beta/backend/tools/install-chat-realtime.sh" --check
if [ "$security_mode" = --check ]; then
    echo 'Apply requires root; five protected PHP files and beta WebSocket service. Cleanup is NOT enabled.'
    exit 0
fi
if [ "$(id -u)" != 0 ]; then echo 'Root on CT 7510 required.' >&2; exit 1; fi
if systemctl is-active --quiet dnct-support-realtime.service; then
    echo 'Beta gateway is already running; review its deployment manually.' >&2
    exit 1
fi
git -c safe.directory="$security_beta" -C "$security_beta" status --short
git -c safe.directory=/var/www/dnct.pl/public_html/WEB/admin.dnct.pl -C /var/www/dnct.pl/public_html/WEB/admin.dnct.pl status --short
patch --batch --forward --backup --suffix=.bak-przed-messaging-security -d / -p1 < "$security_patch"
while IFS= read -r security_file; do php -l "$security_file"; done < <(sed -n 's/^+++ //p' "$security_patch")
bash "$security_beta/backend/tools/install-chat-realtime.sh"
echo 'Beta gateway installed. Verify authenticated chat, ticket and notification flows in the browser.'

