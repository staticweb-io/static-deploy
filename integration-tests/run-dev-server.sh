#!/usr/bin/env bash

set -e

nix develop -c clojure -M:dev-server
