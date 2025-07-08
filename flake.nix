{
  description = "WP2Static";

  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixos-25.05";
    flake-utils.url = "github:numtide/flake-utils";
  };

  outputs = { self, nixpkgs, flake-utils, ... }:
    flake-utils.lib.eachDefaultSystem (system:
      with import nixpkgs { inherit system; };
      with pkgs;
      let
        name = "wp2static";
        version = "8.3.0";
        composerDeps = php.buildComposerProject (finalAttrs: {
          pname = "${name}-composer-deps";
          version = version;
          src = pkgs.lib.cleanSourceWith {
            src = self;
            filter = path: type:
              let rel = baseNameOf path;
              in rel == "composer.json" || rel == "composer.lock";
          };
          vendorHash = "sha256-dIGRVRFG7Rc1HhN0gCiYm1DgHsZAmc1KP490A26Gj2A=";
        });
        wp2staticSrc = pkgs.lib.cleanSourceWith {
          src = self;
          filter = path: type:
            let base = baseNameOf path;
            in type == "directory" && base == "src" || type == "directory"
            && base == "views" || type == "regular"
            && pkgs.lib.hasSuffix ".php" base || base == "composer.json" || base
            == "composer.lock";
        };
        wp2static = runCommand "wp2static" { } ''
          export PLUGIN_DIR="$TMPDIR/${name}"
          mkdir -p "$PLUGIN_DIR"
          cp -r "${composerDeps}/share/php/${name}-composer-deps/vendor" "$PLUGIN_DIR"
          cp -r "${wp2staticSrc}"/* "$PLUGIN_DIR"
          cd "$PLUGIN_DIR"
          chmod 600 vendor/composer/autoload_*.php
          ${phpPackages.composer}/bin/composer dump-autoload --no-dev --optimize
          rm composer.json composer.lock
          mkdir -p $out
          cd "$PLUGIN_DIR"/..
          ${zip}/bin/zip -r -9 $out/wp2static.zip "$(basename "$PLUGIN_DIR")"
        '';
        phpVersions = {
          php81 = php81;
          php82 = php82;
          php83 = php83;
          php84 = php84;
        };
        phpBins = lib.attrsets.mapAttrsToList (name: phpPkg:
          pkgs.writeShellScriptBin name ''${phpPkg}/bin/php "$@"'') phpVersions;
      in {
        devShells.default = mkShell {
          buildInputs = [ php phpPackages.composer shellcheck ] ++ phpBins;
        };
        packages = {
          inherit wp2static;
          plugin = wp2static;
        };
      });
}
