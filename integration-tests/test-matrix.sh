#!/usr/bin/env bash

set -ue

cd "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")"

# shellcheck disable=SC2043
for PHP_VERSION in "8.1"; do
    echo "PHP Version $PHP_VERSION"
    PHP_VERSION=$PHP_VERSION nix develop -c clojure -X:test
done
