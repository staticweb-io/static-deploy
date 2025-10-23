{
  description = "Static Deploy plugin for WordPress";

  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixos-25.05";
    flake-utils.url = "github:numtide/flake-utils";
  };

  outputs =
    {
      self,
      nixpkgs,
      flake-utils,
      ...
    }:
    flake-utils.lib.eachDefaultSystem (
      system:
      with import nixpkgs { inherit system; };
      with pkgs;
      let
        name = "static-deploy";
        version = "9.4.1";
        composerSrc = pkgs.lib.cleanSourceWith {
          src = self;
          filter =
            path: type:
            let
              rel = baseNameOf path;
            in
            rel == "composer.json" || rel == "composer.lock";
        };
        composerVendor = php.mkComposerVendor (finalAttrs: {
          composerNoDev = true;
          pname = "${name}-composer-deps";
          version = "1.0.0";
          src = composerSrc;
          vendorHash = "sha256-XV3pPHX14k7C6epG5uaB7xuEbe9yDfxq5wbKlJBwhtQ=";
        });
        composerVendorDev = php.mkComposerVendor (finalAttrs: {
          composerNoDev = false;
          pname = "${name}-composer-deps-dev";
          version = "1.0.0";
          src = composerSrc;
          vendorHash = "sha256-Sv+hPR9G+NUDXxQr0tqUxIhBWMTCXna6vHHgiJ7ltDs=";
        });
        staticDeploySrc = pkgs.lib.cleanSourceWith {
          src = self;
          filter =
            path: type:
            let
              base = baseNameOf path;
            in
            type == "directory" && base == "src"
            || pkgs.lib.hasInfix "/src/" path
            || type == "directory" && base == "tests"
            || pkgs.lib.hasInfix "/tests/" path
            || type == "directory" && base == "util"
            || pkgs.lib.hasInfix "/util/" path
            || type == "directory" && base == "views"
            || pkgs.lib.hasInfix "/views/" path
            || type == "regular" && pkgs.lib.hasSuffix ".php" base
            || base == "composer.json"
            || base == "composer.lock"
            || base == "justfile"
            || base == "phpcs.xml"
            || base == "phpunit.xml"
            || base == "readme.txt";
        };
        releaseExtras = pkgs.lib.cleanSourceWith {
          src = self;
          filter =
            path: type:
            let
              base = baseNameOf path;
            in
            type == "directory" && base == "release" || pkgs.lib.hasInfix "/release/" path;
        };
        buildStaticDeploySrc =
          constantsFile:
          runCommand "static-deploy-source"
            {
              nativeBuildInputs = [
                just
                php
                phpPackages.composer
              ];
            }
            ''
              export PLUGIN_DIR="$TMPDIR/${name}"
              mkdir -p "$PLUGIN_DIR"
              cp -r --no-preserve=mode "${composerVendorDev}/vendor" .
              cp -r --no-preserve=mode "${staticDeploySrc}"/* .

              # Lock certain constants and run rector to remove dead code
              cp ${constantsFile} constants.php
              just rector

              rm -rf vendor
              cp -r --no-preserve=mode "${composerVendor}/vendor" .
              composer dump-autoload --no-dev --optimize

              mkdir -p "$out"
              cp -r composer.json readme.txt src static-deploy.php uninstall.php vendor views "$out"
            '';
        staticDeployWpOrgSrc = buildStaticDeploySrc "${releaseExtras}/release/wp-org/constants.php";
        staticDeployGitHubSrc = buildStaticDeploySrc "${releaseExtras}/release/github/constants.php";
        pluginZip =
          source:
          runCommand name { } ''
            mkdir "$TMPDIR/${name}"
            cd "$TMPDIR/${name}"
            ln -s "${source}" "${name}"
            mkdir -p "$out"
            ${zip}/bin/zip -r -9 "$out"/"${name}.zip" "${name}"
          '';
        staticDeploy = pluginZip staticDeployGitHubSrc;
        staticDeployWpOrg = pluginZip staticDeployWpOrgSrc;
        staticDeployCheck = stdenv.mkDerivation {
          pname = "static-deploy-check";
          version = version;

          src = staticDeploySrc;

          nativeBuildInputs = [
            bash
            php
          ];
          nativeCheckInputs = [
            jq
            just
            phpPackages.composer
          ];

          doCheck = true;
          dontUseJustInstall = true;

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
            just _phpcs
            # Run directly because composer swallows the exit code
            php vendor/bin/rector --debug --dry-run
          '';
        };
      in
      {
        checks = { inherit staticDeployCheck; };
        lib = { inherit staticDeploySrc; };
        packages = {
          inherit composerVendorDev composerVendor staticDeploy;
          plugin = staticDeploy;
          pluginGitHubSrc = staticDeployGitHubSrc;
          pluginWpOrg = staticDeployWpOrg;
          pluginWpOrgSrc = staticDeployWpOrgSrc;
        };
      }
    );
}
