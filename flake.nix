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
        composerSrc = pkgs.lib.cleanSourceWith {
          src = self;
          filter = path: type:
            let rel = baseNameOf path;
            in rel == "composer.json" || rel == "composer.lock";
        };
        composerHash = "sha256-9sgyD5KnMCUvxelE4RT35gRnxb0Jd+I5oQSrgdmhO84=";
        composerVendor = php.mkComposerVendor (finalAttrs: {
          pname = "${name}-composer-deps";
          version = version;
          src = composerSrc;
          vendorHash = composerHash;
        });
        composerVendorDev = php.mkComposerVendor (finalAttrs: {
          composerNoDev = false;
          pname = "${name}-composer-deps-dev";
          version = version;
          src = composerSrc;
          vendorHash = composerHash;
        });
        wp2staticSrc = pkgs.lib.cleanSourceWith {
          src = wp2staticSrcDev;
          filter = path: type:
            let base = baseNameOf path;
            in type == "directory" && base == "src" || type == "directory"
            && base == "views" || type == "regular"
            && pkgs.lib.hasSuffix ".php" base || base == "composer.json" || base
            == "composer.lock";
        };
        wp2staticSrcDev = pkgs.lib.cleanSourceWith {
          src = self;
          filter = path: type:
            let base = baseNameOf path;
            in type == "directory" && base == "src" || type == "directory"
            && base == "tests" || pkgs.lib.hasInfix "/tests/" path || type
            == "directory" && base == "views" || type == "regular"
            && pkgs.lib.hasSuffix ".php" base || base == "composer.json" || base
            == "composer.lock" || base == "phpunit.xml";
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
      in {
        packages = {
          inherit composerVendorDev composerVendor wp2static wp2staticSrcDev
            wp2staticSrc;
          plugin = wp2static;
        };
      });
}
