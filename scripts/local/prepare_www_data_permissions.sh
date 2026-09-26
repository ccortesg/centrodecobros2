#!/usr/bin/env bash

set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
expected_root="/home/ccortesg/workspace/centrodecobros2"

if [[ "$project_root" != "$expected_root" ]]; then
    echo "Refusing to change permissions outside $expected_root." >&2
    exit 1
fi

if ! getent group www-data >/dev/null; then
    echo "The www-data group does not exist." >&2
    exit 1
fi

cd "$project_root"

sg www-data -c '
    chgrp -R www-data storage bootstrap/cache &&
    chgrp www-data .env &&
    chmod -R g+rwX storage bootstrap/cache &&
    find storage bootstrap/cache -type d -exec chmod g+s {} + &&
    chmod 0640 .env
'

echo "www-data path permissions prepared without global write access."
