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
          vendorHash = "sha256-c7zv3Wprd5rSeDpRLcfBkMEADDrJP5O0XKYQOIk1mSM=";
        });
        composerVendorDev = php.mkComposerVendor (finalAttrs: {
          composerNoDev = false;
          pname = "${name}-composer-deps-dev";
          version = "1.0.0";
          src = composerSrc;
          vendorHash = "sha256-WJ3zfADVJQTLeWLlDBTX34O90SHxLTgIy1nvqUqPIfE=";
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
        wpOrgExtras = pkgs.lib.cleanSourceWith {
          src = self;
          filter = path: type:
            let base = baseNameOf path;
            in type == "directory" && base == "wp-org"
            || pkgs.lib.hasInfix "/wp-org/" path;
        };
        buildStaticDeploySrc = constantsFile:
          runCommand "static-deploy-source" {
            nativeBuildInputs = [ php phpPackages.composer ];
          } ''
            export PLUGIN_DIR="$TMPDIR/${name}"
            mkdir -p "$PLUGIN_DIR"
            cp -r --no-preserve=mode "${composerVendorDev}/vendor" .
            cp -r --no-preserve=mode "${staticDeploySrc}"/* .

            # Lock certain constants and run rector to remove dead code
            cp ${constantsFile} constants.php
            composer rector

            rm -rf vendor
            cp -r --no-preserve=mode "${composerVendor}/vendor" .
            composer dump-autoload --no-dev --optimize

            mkdir -p "$out"
            cp -r src static-deploy.php uninstall.php vendor views "$out"
          '';
        staticDeployWpOrgSrc =
          buildStaticDeploySrc "${wpOrgExtras}/wp-org/constants.php";
        staticDeployGitHubSrc =
          buildStaticDeploySrc "${staticDeploySrc}/constants.php";
        staticDeploy = runCommand "static-deploy" { } ''
          mkdir "$TMPDIR/${name}"
          cd "$TMPDIR/${name}"
          ln -s "${staticDeployGitHubSrc}" "${name}"
          mkdir -p $out
          ${zip}/bin/zip -r -9 $out/static-deploy.zip "${name}"
        '';
        staticDeployCheck = stdenv.mkDerivation {
          pname = "static-deploy-check";
          version = version;

          src = staticDeploySrc;

          nativeBuildInputs = [ bash php ];
          nativeCheckInputs = [ jq phpPackages.composer ];

          doCheck = true;

          buildPhase = ''
            mkdir -p $out
          '';

          checkPhase = ''
            export PLUGIN_DIR="$TMPDIR/${name}"
            mkdir -p "$PLUGIN_DIR"
            cd "$PLUGIN_DIR"
            cp -a "${composerVendorDev}/vendor" .
            cp -r --no-preserve=mode "$src"/* .
            composer lint
            composer phpcs
            # Run directly because composer swallows the exit code
            php vendor/bin/rector --debug --dry-run
          '';
        };
      in {
        checks = { inherit staticDeployCheck; };
        lib = { inherit staticDeploySrc; };
        packages = {
          inherit composerVendorDev composerVendor staticDeploy;
          plugin = staticDeploy;
          pluginGitHubSrc = staticDeployGitHubSrc;
          pluginWpOrgSrc = staticDeployWpOrgSrc;
        };
      });
}
