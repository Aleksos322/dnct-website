#!/usr/bin/env bash
# Uruchom na CT 7510 jako root. --check tylko pokazuje zakres zmian.
set -euo pipefail
chat_source=/var/www/dnct.pl/public_html/WEB/beta.dnct.pl
node --check "$chat_source/backend/lib/chat-realtime-server.mjs"
if [ "${1:-}" != "--check" ]; then
    if [ "$(id -u)" != "0" ]; then
        echo "Wymagany root na CT 7510. Podgląd: bash $0 --check" >&2
        exit 1
    fi
    if systemctl is-active --quiet dnct-support-realtime.service; then
        echo "Usługa bety już działa. Przerwano, aby nie restartować działającego czatu." >&2
        exit 1
    fi
fi
python3 - "${1:-apply}" <<'PY'
from pathlib import Path
import re,shutil,sys
preview=sys.argv[1]=='--check'
targets=[
 ('/etc/apache2/sites-available/private-8910.conf','beta.dnct.pl',False),
 ('/etc/apache2/sites-available/admin.dnct.pl.conf','admin.lan.dnct.pl',True),
]
changes=[]
for filename,host,lan in targets:
    p=Path(filename);source=p.read_text()
    def patch(m):
        block=m.group(0)
        if 'ServerName '+host not in block or (lan and '*:443' not in block.splitlines()[0]):return block
        if 'ProxyPass "/ws/support"' in block:
            if 'ws://127.0.0.1:8981/ws/support' not in block:raise RuntimeError('Istnieje inny cel WebSocket: '+filename)
            return block
        rules=''
        if lan:
            match=re.search(r'<RequireAll>.*?</RequireAll>',block,re.S)
            if not match:raise RuntimeError('Brak reguł LAN; odmowa zmiany')
            rules='\n    <Location "/ws/support">\n        '+match.group(0)+'\n    </Location>\n'
        addition='\n    # DNCT beta: prywatny czat i zgłoszenia, gateway wyłącznie loopback.\n    ProxyPass "/ws/support" "ws://127.0.0.1:8981/ws/support" timeout=3600\n    ProxyPassReverse "/ws/support" "ws://127.0.0.1:8981/ws/support"\n'+rules
        return block.replace('</VirtualHost>',addition+'</VirtualHost>')
    result=re.sub(r'<VirtualHost\b[^>]*>.*?</VirtualHost>',patch,source,flags=re.S)
    if result==source:
        print(filename+': konfiguracja już obecna')
    else:
        print(filename+': dodanie /ws/support -> 127.0.0.1:8981'+(' z zachowaniem blokady LAN/VPN' if lan else ''))
        changes.append((p,source,result))
if not preview:
    for p,source,result in changes:
        backup=p.with_name(p.name+'.bak-przed-czat-realtime')
        if not backup.exists():shutil.copy2(p,backup)
        if p.read_text()!=source:raise RuntimeError('Plik zmienił się podczas przygotowania: '+str(p))
        p.write_text(result)
PY
if [ "${1:-}" = "--check" ]; then
    echo "Usługa: dnct-support-realtime.service; bind: 127.0.0.1:8981; produkcja 8982 bez zmian."
    exit 0
fi
a2enmod proxy proxy_http proxy_wstunnel
apache2ctl configtest
service_target=/etc/systemd/system/dnct-support-realtime.service
if [ -f "$service_target" ] && [ ! -f "$service_target.bak-przed-czat-realtime" ]; then
    cp -p "$service_target" "$service_target.bak-przed-czat-realtime"
fi
install -o root -g root -m 644 "$chat_source/backend/lib/dnct-support-realtime.service" "$service_target"
systemctl daemon-reload
systemctl enable --now dnct-support-realtime.service
systemctl reload apache2
systemctl --no-pager --full status dnct-support-realtime.service
ss -lntp | rg ':8981'
