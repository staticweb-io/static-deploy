repo_root := `pwd`

# List available recipes
help:
  # First command in the file is invoked by default
  @just --list

# Format source and then check for unfixable issues
format:
  just --fmt --unstable
  just phpcbf || true && just phpcs

update-composer-deps: && update-hashes
  composer update

update-deps: update-flakes update-composer-deps

update-flakes:
  nix flake update
  fd flake.nix -j 4 -x bash -c 'echo "Updating flake inputs in {//}"; cd "{//}" && nix flake update --inputs-from "$0"' "{{repo_root}}"

update-hashes:
  ./bin/update-hashes

update-json:
  ./bin/update-json
