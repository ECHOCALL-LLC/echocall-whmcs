#!/bin/sh
# Builds build/echocall-whmcs.zip from the committed state. The ZIP is extracted
# into the WHMCS root, so it holds modules/servers/echocall/ and nothing else:
# the module, the license and, when a path is given, the WordPress plugin ZIP
# that clients download from the client area.
#
#   sh build-zip.sh [path/to/echocall-chat-widget.zip]
set -eu

module=modules/servers/echocall
plugin=build/plugin/echocall-chat-widget.zip

rm -rf build
mkdir -p build/plugin

if [ $# -gt 0 ]; then
  if [ ! -s "$1" ]; then
    echo "The WordPress plugin ZIP $1 is missing or empty."
    exit 1
  fi
  # --add-file keeps the base name, so the plugin is copied under its fixed name first.
  cp "$1" "$plugin"
  set -- "--prefix=$module/wordpress-plugin/" "--add-file=$plugin"
fi

# The rightmost --prefix applies to the committed files, every --add-file uses
# the --prefix in front of it.
git archive --format=zip -o build/echocall-whmcs.zip \
  "$@" \
  "--prefix=$module/" --add-file=LICENSE \
  --prefix= HEAD "$module"

echo "Built build/echocall-whmcs.zip"
