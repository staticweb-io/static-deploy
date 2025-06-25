#!/usr/bin/env bash

set -e

WP2STATIC_SYMLINK=t nix develop -c clojure -M:dev-server
