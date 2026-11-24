#!/bin/sh
# The module version is written in several files. They have to agree, and on a
# release they have to agree with the tag as well: sh check-versions.sh v2.0.0
set -eu

m=modules/servers/echocall
api=$(sed -n "s/^ *public const VERSION = '\([0-9][0-9.]*\)';$/\1/p" "$m/lib/EchoCallAPI.php")
status=0

check() {
  # $1 label, $2 version found in that place
  echo "$1: ${2:-missing}"
  if [ -z "$2" ] || [ "$2" != "$api" ]; then
    status=1
  fi
}

check "EchoCallAPI::VERSION" "$api"
check "echocall.php @version" "$(sed -n 's/^ \* @version *\([0-9][0-9.]*\)$/\1/p' "$m/echocall.php")"
check "hooks.php @version" "$(sed -n 's/^ \* @version *\([0-9][0-9.]*\)$/\1/p' "$m/hooks.php")"
check "echocall.js header" "$(sed -n 's/^ \* EchoCall client area for WHMCS, module \([0-9][0-9.]*\)\.$/\1/p' "$m/assets/js/echocall.js")"
check "main.tpl header" "$(sed -n '1s/^{\* EchoCall client area, module \([0-9][0-9.]*\)\..*/\1/p' "$m/templates/clientarea/main.tpl")"
check "README.md" "$(sed -n 's/^Version \([0-9][0-9.]*\)$/\1/p' README.md)"
check "INSTALL.md" "$(sed -n 's/^Version \([0-9][0-9.]*\) for WHMCS.*/\1/p' INSTALL.md)"
check "newest CHANGELOG.md section" "$(sed -n 's/^## \[\([0-9][0-9.]*\)\].*/\1/p' CHANGELOG.md | head -n 1)"

if [ "$status" -ne 0 ]; then
  echo "The versions do not agree."
  exit 1
fi

if [ $# -gt 0 ] && [ "v$api" != "$1" ]; then
  echo "The tag $1 does not match version $api."
  exit 1
fi

echo "All versions are $api."
