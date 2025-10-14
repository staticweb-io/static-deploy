repo_root := `pwd`

alias b := build
alias fmt := format

# List available recipes
help:
    # First command in the file is invoked by default
    @just --list

# Build plugin zip
build:
    nix build .#plugin

# Format source and then check for unfixable issues
format:
    just --fmt --unstable
    just _phpcbf || true && just _phpcs

_phpcbf:
    php ./vendor/bin/phpcbf -d memory_limit=512M --standard=./phpcs.xml --extensions=php src tests views *.php

_phpcs:
    php ./vendor/bin/phpcs -d memory_limit=512M -s --standard=./phpcs.xml --extensions=php src tests views *.php

# Run rector code transformations
rector:
    # We sometimes get errors running without --debug
    php ./vendor/bin/rector --debug

_update-composer-deps: && update-hashes
    composer update

# Upgrade dependencies
update-deps: _update-flakes _update-composer-deps

_update-flakes:
    nix flake update
    fd flake.nix -j 4 -x bash -c 'echo "Updating flake inputs in {//}"; cd "{//}" && nix flake update --inputs-from "$0"' "{{ repo_root }}"

# Update the vendorHash after a composer.json change
update-hashes:
    ./bin/update-hashes

# Update the update.json file with current values
update-json:
    ./bin/update-json
