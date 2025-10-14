repo_root := `pwd`

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
    just phpcbf || true && just phpcs

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
