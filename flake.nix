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
          vendorHash = "sha256-4eHxmlB7KQsEn1gCTGPJzklnYTrRzpI+Pt8ccrl7Jkc=";
        });
        composerVendorDev = php.mkComposerVendor (finalAttrs: {
          composerNoDev = false;
          pname = "${name}-composer-deps-dev";
          version = "1.0.0";
          src = composerSrc;
          vendorHash = "sha256-4M4bKmJMZyM+DMVIUwKdbhC6rI+xIDtAtWHu1l9JAS4=";
        });
        staticDeploySrc = pkgs.lib.cleanSourceWith {
          src = self;
          filter = path: type:
            let base = baseNameOf path;
            in type == "directory" && base == "src"
            || pkgs.lib.hasInfix "/src/" path || type == "directory" && base
            == "tests" || pkgs.lib.hasInfix "/tests/" path || type
            == "directory" && base == "util" || pkgs.lib.hasInfix "/util/" path
            || type == "directory" && base == "views"
            || pkgs.lib.hasInfix "/views/" path || type == "regular"
            && pkgs.lib.hasSuffix ".php" base || base == "composer.json" || base
            == "composer.lock" || base == "phpcs.xml" || base == "phpunit.xml";
        };
        # Sources used for GitHub releases but not for WordPress.org
        staticDeploySrcGitHub = pkgs.lib.cleanSourceWith {
          src = self;
          filter = path: type:
            let base = baseNameOf path;
            in type == "directory" && base == "src-github"
            || pkgs.lib.hasInfix "/src-github/" path;
        };
        wpOrgExtras = pkgs.lib.cleanSourceWith {
          src = self;
          filter = path: type:
            let base = baseNameOf path;
            in type == "directory" && base == "wp-org"
            || pkgs.lib.hasInfix "/wp-org/" path;
        };
        staticDeployWpOrgSrc = runCommand "static-deploy-wp-org-src" {
          nativeBuildInputs = [ php phpPackages.composer ];
        } ''
          export PLUGIN_DIR="$TMPDIR/${name}"
          mkdir -p "$PLUGIN_DIR"
          cp -r --no-preserve=mode "${composerVendorDev}/vendor" .
          cp -r --no-preserve=mode "${staticDeploySrc}"/* .

          # Lock certain constants and run rector to remove dead code
          cp ${wpOrgExtras}/wp-org/constants.php constants.php
          mkdir src-github # Prevent an error
          composer rector

          mkdir -p "$out"
          cp -r src static-deploy.php uninstall.php "$out"
          # Add release deps
          cp -r "${composerVendor}/vendor" "$out"
        '';
        staticDeploy = runCommand "static-deploy" { } ''
          export PLUGIN_DIR="$TMPDIR/${name}"
          mkdir -p "$PLUGIN_DIR"
          cp -r --no-preserve=mode "${staticDeploySrc}"/* "$PLUGIN_DIR"
          cp -r --no-preserve=mode "${staticDeploySrcGitHub}"/src-github/* "$PLUGIN_DIR"/src
          cd "$PLUGIN_DIR"
          ${phpPackages.composer}/bin/composer dump-autoload --no-dev --optimize
          rm composer.json composer.lock
          mkdir -p $out
          cd "$PLUGIN_DIR"/..
          ${zip}/bin/zip -r -9 $out/static-deploy.zip "$(basename "$PLUGIN_DIR")"
        '';
        staticDeployCheck = stdenv.mkDerivation {
          pname = "static-deploy-check";
          version = version;

          src = staticDeploySrc;

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
        lib = { inherit staticDeploySrc; };
        packages = {
          inherit composerVendorDev composerVendor staticDeploy;
          plugin = staticDeploy;
          pluginWpOrgSrc = staticDeployWpOrgSrc;
        };
      });
}
