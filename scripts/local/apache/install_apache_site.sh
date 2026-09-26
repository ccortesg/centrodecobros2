#!/usr/bin/env bash

set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    echo "Run this installer with sudo." >&2
    exit 1
fi

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
expected_root="/home/ccortesg/workspace/centrodecobros2"
site_source="$project_root/scripts/local/apache/centrodecobros2.conf"
site_target="/etc/apache2/sites-available/centrodecobros2.conf"

if [[ "$project_root" != "$expected_root" || ! -f "$site_source" ]]; then
    echo "Refusing to install from an unexpected project path." >&2
    exit 1
fi

install -o root -g root -m 0644 "$site_source" "$site_target"
a2enmod rewrite >/dev/null
a2ensite centrodecobros2.conf >/dev/null
apache2ctl configtest
systemctl reload apache2

echo "Apache site installed and reloaded."
