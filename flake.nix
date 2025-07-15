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
        version = "8.4.0";
        composerSrc = pkgs.lib.cleanSourceWith {
          src = self;
          filter = path: type:
            let rel = baseNameOf path;
            in rel == "composer.json" || rel == "composer.lock";
        };
        composerVendor = php.mkComposerVendor (finalAttrs: {
          composerNoDev = true;
          pname = "${name}-composer-deps";
          version = "1.0.0";
          src = composerSrc;
          vendorHash = "sha256-9C0sKZKSO4V3qPvUIFbgwTqbEuDSEBYwjbMQKf+tH5M=";
        });
        composerVendorDev = php.mkComposerVendor (finalAttrs: {
          composerNoDev = false;
          pname = "${name}-composer-deps-dev";
          version = "1.0.0";
          src = composerSrc;
          vendorHash = "sha256-zYhMb7wdBosEIEnHNum8wEu+BmNi1AF7AFp3r1sFRPA=";
        });
        wp2staticSrc = pkgs.lib.cleanSourceWith {
          src = self;
          filter = path: type:
            let base = baseNameOf path;
            in type == "directory" && base == "src"
            || pkgs.lib.hasInfix "/src/" path || type == "directory" && base
            == "views" || type == "regular" && pkgs.lib.hasSuffix ".php" base
            || base == "composer.json" || base == "composer.lock";
        };
        wp2staticSrcDev = pkgs.lib.cleanSourceWith {
          src = self;
          filter = path: type:
            let base = baseNameOf path;
            in type == "directory" && base == "src" || type == "directory"
            && base == "tests" || pkgs.lib.hasInfix "/tests/" path || type
            == "directory" && base == "views" || type == "regular"
            && pkgs.lib.hasSuffix ".php" base || base == "composer.json" || base
            == "composer.lock" || base == "phpcs.xml" || base == "phpunit.xml";
        };
        wp2static = runCommand "wp2static" { } ''
          export PLUGIN_DIR="$TMPDIR/${name}"
          mkdir -p "$PLUGIN_DIR"
          cp -r "${composerVendor}/vendor" "$PLUGIN_DIR"
          cp -r "${wp2staticSrc}"/* "$PLUGIN_DIR"
          cd "$PLUGIN_DIR"
          chmod 600 vendor/composer/autoload_*.php
          ${phpPackages.composer}/bin/composer dump-autoload --no-dev --optimize
          rm composer.json composer.lock
          mkdir -p $out
          cd "$PLUGIN_DIR"/..
          ${zip}/bin/zip -r -9 $out/wp2static.zip "$(basename "$PLUGIN_DIR")"
        '';
        wp2staticCheck = stdenv.mkDerivation {
          pname = "wp2static-check";
          version = version;

          src = wp2staticSrcDev;

          nativeBuildInputs = [ bash php ];

          doCheck = true;

          buildPhase = ''
            mkdir -p $out
          '';

          checkPhase = ''
            export PLUGIN_DIR="$TMPDIR/${name}"
            mkdir -p "$PLUGIN_DIR"
            cd "$PLUGIN_DIR"
            cp -a "${composerVendorDev}/vendor" .
            cp -a "$src"/* .
            ${phpPackages.composer}/bin/composer lint
            ${phpPackages.composer}/bin/composer phpcs
          '';
        };
      in {
        checks = { inherit wp2staticCheck; };
        lib = { inherit wp2staticSrcDev wp2staticSrc; };
        packages = {
          inherit composerVendorDev composerVendor wp2static;
          plugin = wp2static;
        };
      });
}
