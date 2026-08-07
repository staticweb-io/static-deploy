{
  description = "Static Deploy plugin for WordPress";

  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixos-26.05";
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
        name = "staticweb-deploy";
        version = "9.9.4";
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
          composerNoDev = false;
          pname = "${name}-composer-deps-dev";
          version = "1.0.0";
          src = composerSrc;
          vendorHash = "sha256-/9PGCA4AmI3ja69BPWHI+VefM7Fq4UCchn9Oc3ftEpU=";
        });
        # Plugin source, without tests. Tests are not shipped in the built
        # plugin, so keeping them out of this derivation stops test-only edits
        # from invalidating the plugin build.
        staticDeploySrc = pkgs.lib.cleanSourceWith {
          src = self;
          filter =
            path: type:
            let
              base = baseNameOf path;
            in
            type == "directory" && base == "src"
            || pkgs.lib.hasInfix "/src/" path
            || type == "directory" && base == "util"
            || pkgs.lib.hasInfix "/util/" path
            || type == "directory" && base == "views"
            || pkgs.lib.hasInfix "/views/" path
            || type == "regular" && pkgs.lib.hasSuffix ".php" base
            || base == "composer.json"
            || base == "composer.lock"
            || base == "justfile"
            || base == "phpcs.xml"
            || base == "readme.txt";
        };
        # Tests only, copied in on top of staticDeploySrc for the checks and
        # the dev test harness.
        staticDeployTestsSrc = pkgs.lib.cleanSourceWith {
          src = self;
          filter =
            path: type:
            let
              base = baseNameOf path;
            in
            type == "directory" && base == "tests" || pkgs.lib.hasInfix "/tests/" path || base == "phpunit.xml";
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
          constantsFile: preComposerInstall:
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
              cp -r --no-preserve=mode "${composerVendor}/vendor" .
              cp -r --no-preserve=mode "${staticDeploySrc}"/* .

              # mkComposerVendor strips vendor/bin/
              COMPOSER_DISABLE_NETWORK=1 composer --no-cache --no-interaction --optimize-autoloader install

              # Lock certain constants and run rector to remove dead code
              cp ${constantsFile} constants.php
              just rector

              ${preComposerInstall}
              COMPOSER_DISABLE_NETWORK=1 composer --no-dev --no-cache --no-interaction --optimize-autoloader install

              mkdir -p "$out"
              cp -r composer.json readme.txt src staticweb-deploy.php uninstall.php vendor views "$out"
            '';
        staticDeployWpOrgSrc = buildStaticDeploySrc "${releaseExtras}/release/wp-org/constants.php" ''
          composer remove yahnis-elsts/plugin-update-checker --minimal-changes --no-cache
          sed -i '/^use YahnisElsts\\PluginUpdateChecker/d' src/WordPressAdmin.php
          just rector -c rector-downgrade.php
        '';
        staticDeployGitHubSrc = buildStaticDeploySrc "${releaseExtras}/release/github/constants.php" "";
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
            cp -r --no-preserve=mode "${composerVendor}/vendor" "$src"/* "${staticDeployTestsSrc}"/* .
            COMPOSER_DISABLE_NETWORK=1 composer --no-cache --no-interaction --optimize-autoloader install
            just _check_no_test
          '';
        };
      in
      {
        checks = { inherit staticDeployCheck; };
        packages = {
          inherit composerVendor staticDeploy;
          plugin = staticDeploy;
          pluginDevSrc = runCommand "static-deploy-dev-src" { } ''
            mkdir -p $out
            cp -r ${staticDeploySrc}/* $out/
            cp -r ${staticDeployTestsSrc}/* $out/
          '';
          pluginGitHubSrc = staticDeployGitHubSrc;
          pluginWpOrg = staticDeployWpOrg;
          pluginWpOrgSrc = staticDeployWpOrgSrc;
        };
      }
    );
}
