{
  description = "integration tests";

  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixos-25.05";
    flake-utils.url = "github:numtide/flake-utils";
    wp2static.url = "..";
  };

  outputs = { self, nixpkgs, flake-utils, wp2static, ... }:
    flake-utils.lib.eachDefaultSystem (system:
      with import nixpkgs { inherit system; };
      with pkgs;
      let
        getEnv = name: default:
          (if "" == builtins.getEnv name then
            default
          else
            builtins.getEnv name);
        local-mariadb-install = replaceVarsWith {
          dir = "bin";
          isExecutable = true;
          meta.mainProgram = "local-mariadb-install";
          name = "local-mariadb-install";
          replacements = {
            inherit bash mariadb;
            cnf-file = self + "/mariadb/my.cnf";
          };
          src = self + "/src/local-mariadb-install.sh";
        };
        local-mariadb-run = replaceVarsWith {
          dir = "bin";
          isExecutable = true;
          meta.mainProgram = "local-mariadb-run";
          name = "local-mariadb-run";
          replacements = {
            inherit bash mariadb;
            cnf-file = self + "/mariadb/my.cnf";
          };
          src = self + "/src/local-mariadb-run.sh";
        };
        composerPackages = { "8.1" = php81Packages.composer; };
        phpPackages = { "8.1" = pkgs.php81; };
        phpVersion = getEnv "PHP_VERSION" "8.1";
        composer = lib.getAttr phpVersion composerPackages;
        php = lib.getAttr phpVersion phpPackages;
        wordpress = (pkgs.wordpress.overrideAttrs (oldAttrs: rec {
          version = getEnv "WORDPRESS_VERSION" "6.0.1";
          src = fetchurl {
            url = "https://wordpress.org/wordpress-${version}.tar.gz";
            sha256 = getEnv "WORDPRESS_SHA256"
              "f678596804aa89d7cdc9280862938464eab25aeaebfefa91ae175e15aa3ef054";
          };
        }));
      in {
        devShells.default = mkShell {
          buildInputs = [
            (clojure.override { jdk = jdk_headless; })
            composer
            git
            local-mariadb-install
            local-mariadb-run
            mariadb
            nginx
            php
            rlwrap
            unzip
            wordpress
            wp-cli
            zip
          ];
          WORDPRESS_PATH = wordpress;
          WP2STATIC_PATH = wp2static.packages.${system}.plugin;
        };
      });
}
