#!/usr/bin/env bash
# Installs (or removes) the core comment type shim into the wp-env tests container.
#
#   bin/comment-type-shim/install.sh          install
#   bin/comment-type-shim/install.sh remove   remove
#
# Nothing under wp-includes/ is touched. The shim is an mu-plugin.
set -euo pipefail

# wp-env names containers by a hash of the project directory, so ask it rather than grepping docker.
# `$HOSTNAME` inside the container is its short id; resolve that to the name docker uses.
CONTAINER_ID="$(npx wp-env run tests-cli sh -c 'echo $HOSTNAME' 2>/dev/null | tail -1)"
CONTAINER="$(docker ps --filter "id=${CONTAINER_ID}" --format '{{.Names}}' | head -1)"
if [ -z "$CONTAINER" ]; then
	echo "no wp-env tests container running; start it with: npx wp-env start" >&2
	exit 1
fi
TARGET=/var/www/html/wp-content/mu-plugins/zz-comment-type-core-shim.php

if [ "${1:-install}" = "remove" ]; then
	docker exec "$CONTAINER" rm -f "$TARGET"
	echo "removed $TARGET"
	exit 0
fi

docker cp "$(dirname "$0")/zz-comment-type-core-shim.php" "$CONTAINER:$TARGET"
echo "installed $TARGET"
echo "polyfill branch now inactive; the plugin runs against the shim's core definitions"
