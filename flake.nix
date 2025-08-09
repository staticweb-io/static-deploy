{
  description = "Static Deploy plugin for WordPress";

  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixos-25.05";
    flake-utils.url = "github:numtide/flake-utils";
  };

  outputs = { self, nixpkgs, flake-utils, ... }:
    flake-utils.lib.eachDefaultSystem (system:
      with import nixpkgs { inherit system; };
      with pkgs;
      let
        name = "static-deploy";
        version = "9.3.2";
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
          vendorHash = "sha256-Yv/yvbN7hVtUkkzZFcDmQnQmXYxdZBvm3TJ+dvfZC7Y=";
        });
        composerVendorDev = php.mkComposerVendor (finalAttrs: {
          composerNoDev = false;
          pname = "${name}-composer-deps-dev";
          version = "1.0.0";
          src = composerSrc;
          vendorHash = "sha256-/OHhfrQZyWvLUMWPQwPQ6z4xxXZMKa6fMLIwycRyW6Y=";
        });
        staticDeploySrc = pkgs.lib.cleanSourceWith {
          src = self;
          filter = path: type:
            let base = baseNameOf path;
            in type == "directory" && base == "src"
            || pkgs.lib.hasInfix "/src/" path || type == "directory" && base
            == "views" || pkgs.lib.hasInfix "/views/" path || type == "regular"
            && pkgs.lib.hasSuffix ".php" base || base == "composer.json" || base
            == "composer.lock";
        };
        # Sources used for GitHub releases but not for WordPress.org
        staticDeploySrcGitHub = pkgs.lib.cleanSourceWith {
          src = self;
          filter = path: type:
            let base = baseNameOf path;
            in type == "directory" && base == "src-github"
            || pkgs.lib.hasInfix "/src-github/" path;
        };
        staticDeploySrcDev = pkgs.lib.cleanSourceWith {
          src = self;
          filter = path: type:
            let base = baseNameOf path;
            in type == "directory" && base == "src"
            || pkgs.lib.hasInfix "/src/" path || type == "directory" && base
            == "tests" || pkgs.lib.hasInfix "/tests/" path || type
            == "directory" && base == "views"
            || pkgs.lib.hasInfix "/views/" path || type == "regular"
            && pkgs.lib.hasSuffix ".php" base || base == "composer.json" || base
            == "composer.lock" || base == "phpcs.xml" || base == "phpunit.xml";
        };
        staticDeployWpOrgSrc = runCommand "static-deploy" { } ''
          mkdir -p $out
          cp -r "${composerVendor}/vendor" "$out"
          cp -r "${staticDeploySrc}"/* "$out"
        '';
        staticDeploy = runCommand "static-deploy" { } ''
          export PLUGIN_DIR="$TMPDIR/${name}"
          mkdir -p "$PLUGIN_DIR"
          cp -r --dereference --no-preserve=mode,ownership "${staticDeployWpOrgSrc}"/* "$PLUGIN_DIR"
          cp -r --dereference --no-preserve=mode,ownership "${staticDeploySrcGitHub}"/src-github/* "$PLUGIN_DIR"/src
          cd "$PLUGIN_DIR"
          chmod 600 vendor/composer/autoload_*.php
          ${phpPackages.composer}/bin/composer dump-autoload --no-dev --optimize
          rm composer.json composer.lock
          mkdir -p $out
          cd "$PLUGIN_DIR"/..
          ${zip}/bin/zip -r -9 $out/static-deploy.zip "$(basename "$PLUGIN_DIR")"
        '';
        staticDeployCheck = stdenv.mkDerivation {
          pname = "static-deploy-check";
          version = version;

          src = staticDeploySrcDev;

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
        checks = { inherit staticDeployCheck; };
        lib = { inherit staticDeploySrcDev staticDeploySrc; };
        packages = {
          inherit composerVendorDev composerVendor staticDeploy;
          plugin = staticDeploy;
          pluginWpOrgSrc = staticDeployWpOrgSrc;
        };
      });
}
