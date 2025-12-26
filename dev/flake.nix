{
  inputs = {
    nixos2505.url = "github:nixos/nixpkgs/nixos-25.05";
    nixpkgs.url = "github:nixos/nixpkgs/nixos-25.11";
    flake-parts.url = "github:hercules-ci/flake-parts";
    systems.url = "github:nix-systems/default";
    hyperfine-flake = {
      inputs.nixpkgs.follows = "nixpkgs";
      url = "github:john-shaffer/hyperfine-flake";
    };
    process-compose-flake.url = "github:Platonic-Systems/process-compose-flake";
    services-flake.url = "github:juspay/services-flake";
    wordpress-flake = {
      inputs.nixpkgs.follows = "nixpkgs";
      url = "github:staticweb-io/wordpress-flake";
    };
    static-deploy.url = ./..;
  };
  outputs =
    inputs:
    let
      dbName = "wordpress";
      dbPort = 3306;
      dbUserName = "wordpress";
      dbUserPass = "8BVMm2jqDE6iADNyfaVCxoCzr3eBY6Ep";
      serverPort = 8888;
      memcachedConfig = {
        enable = true;
        maxMemory = 100;
        port = 11211;
      };
      mysqlConfig = {
        enable = true;
        ensureUsers = [
          {
            name = "www";
            ensurePermissions = {
              "${dbName}.*" = "ALL PRIVILEGES";
            };
          }
        ];
        initialDatabases = [ { name = dbName; } ];
      };
      # Note that /tmp/xd has to be created to receive traces
      phpOptions = ''
        opcache.interned_strings_buffer = 16
        opcache.jit = 1255
        opcache.jit_buffer_size = 8M
        upload_max_filesize=1024M
      '';
    in
    inputs.flake-parts.lib.mkFlake { inherit inputs; } {
      systems = import inputs.systems;
      imports = [ inputs.process-compose-flake.flakeModule ];
      perSystem =
        {
          self',
          pkgs,
          config,
          lib,
          ...
        }:
        let
          system = pkgs.stdenv.hostPlatform.system;
          nixpkgs2505 = import inputs.nixos2505 { };
          getEnv = name: default: (if "" == builtins.getEnv name then default else builtins.getEnv name);
          phpPackage = getEnv "PHP_PACKAGE" "php";
          phpExtensionsName = phpPackage + "Extensions";
          staticDeployPackage = getEnv "STATIC_DEPLOY_PACKAGE" "pluginWpOrg";
          wordpressPackage = getEnv "WORDPRESS_PACKAGE" "default";
          staticDeployPkgs = inputs.static-deploy.packages.${system};
          staticDeploy = staticDeployPkgs.${staticDeployPackage};
          # Note that /tmp/xd has to be created to receive traces
          phpOptions = ''
            opcache.interned_strings_buffer = 16
            opcache.jit = 1255
            opcache.jit_buffer_size = 8M
            upload_max_filesize=1024M
          '';
          phpExtensionsFn =
            { enabled, all }:
            enabled
            ++ (with all; [
              apcu
              imagick
              memcached
            ]);
          otherPhpVersionsOverlay = self: super: {
            php81 = nixpkgs2505.php81;
            php81Extensions = nixpkgs2505.php81Extensions;
            php81Packages = nixpkgs2505.php81Packages;
          };
          overlay = self: super: {
            php = super.${phpPackage}.buildEnv {
              extraConfig = phpOptions;
              extensions = phpExtensionsFn;
            };
            phpIniFile = pkgs.runCommand "php.ini" { preferLocalBuild = true; } ''
              cat ${self.php}/etc/php.ini > $out
            '';
            wp-cli = super.wp-cli.override { phpIniFile = self.phpIniFile; };
          };
          finalPkgs = import pkgs.path {
            inherit (pkgs) system;
            overlays = [
              otherPhpVersionsOverlay
              overlay
            ];
          };
          phpExtensions = finalPkgs.${phpExtensionsName};
        in
        with finalPkgs;
        let
          nginxHttpConfig = data-root: phpfpm-socket: ''
            server {
              listen ${toString serverPort} default_server;

              server_name _;

              root ${data-root};

              index index.php index.html index.htm;

              client_max_body_size 1024M;

              location / {
                  try_files $uri $uri/ =404;

                  if (!-e $request_filename) {
                      rewrite ^(.+)$ /index.php?q=$1 last;
                  }
              }

              location ~ \.php$ {
                fastcgi_split_path_info ^(.+\.php)(/.+)$;
                fastcgi_pass unix:${phpfpm-socket};
                include ${pkgs.nginx}/conf/fastcgi.conf;
              }

              location ~ /\.ht {
                deny all;
              }
            }
          '';
          WPConfigFormat =
            (inputs.wordpress-flake.lib.WPConfigFormat {
              inherit pkgs lib;
            }).format
              { };
          update-wordpress = inputs.wordpress-flake.packages.${system}.update-wordpress;
          wordpress = inputs.wordpress-flake.packages.${system}.${wordpressPackage};
          wpConfig =
            dbHost: dbUserName:
            inputs.wordpress-flake.lib.mkWPConfig {
              inherit pkgs lib;
              name = "wp-config.php";
              settings = {
                DB_HOST = dbHost;
                DB_NAME = dbName;
                DB_USER = dbUserName;
                DB_PASSWORD = dbUserPass;
                WP_AUTO_UPDATE_CORE = false;

                AUTH_KEY = "A6tr^0=N<QP++W-%/hv1yOZ4]f<3m`/}0(A/UFi6pmy|ZLT)=>e+raWRmgYCs>aK";
                SECURE_AUTH_KEY = "Vj>>M=2uvzzWw-tqT?]H3RWsG%jTA9EhJKn~F6:8B<So+<A_},Y<RW-U)}/w-0Y+";
                LOGGED_IN_KEY = "jJCaP}~YG-Se+<WK5g9.@K*^g7*v=_yLyX7+i?{Mc%CcJ|L54u=+*+rW_Uxa{95L";
                NONCE_KEY = "98.DYg|E,*CV]Rz&#Q{j]?n[!sQji*X9%`Ic_n>NExS<7Sn[SG:`P8)*CqC[G2NF";
                AUTH_SALT = "*KON9~cuX+lG,Kx6`^5d#kyu5oFt{^~O:[]pB]F745S<B2U*L0aHb;(pEn:kPggf";
                SECURE_AUTH_SALT = "MV6l72,Yi+y8X`0wm5-T)6T#ZY~Sp;G+e3. ^CHdZ1W_*WY?;9>c}^|:[<j0FkpV";
                LOGGED_IN_SALT = "Don!4M=(5=Y=*@.NI:bn$V[FZ*a~wyJ:s9p&l@XD{7WzqBDO.3+-#[H>79,rG)Q~";
                NONCE_SALT = "t={*XeC6q4LZ5:%wo*C3f-sr6g3#Wa}_EMf}Jh$8*P/%4SdK4=0hjjnVa&8yY#-F";
                WP_CACHE_KEY_SALT = ")O~B@EKC(tfdgDg6R8@6;ePxJJkXMpZ&.u?X{j##:@7-,/*YKvvl-l4}r^@2=Ha-";

                WP_CACHE = true;
                HTTP_HOST = WPConfigFormat.lib.mkInline ''
                  if ( defined( 'WP_CLI' ) ) {
                      $_SERVER['HTTP_HOST'] = isset( $_ENV['HTTP_HOST'] ) ? $_ENV['HTTP_HOST'] : 'localhost:${toString serverPort}';
                  }
                '';
                WP_HOME = WPConfigFormat.lib.mkInline ''
                  if ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ) {
                      define( 'WP_HOME', 'https://' . $_SERVER['HTTP_HOST'] . '/' );
                  } else {
                      define( 'WP_HOME', 'http://' . $_SERVER['HTTP_HOST'] . '/' );
                  }
                '';
                WP_SITEURL = WPConfigFormat.lib.mkInline ''
                  if ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ) {
                      define( 'WP_SITEURL', 'https://' . $_SERVER['HTTP_HOST'] . '/' );
                  } else {
                      define( 'WP_SITEURL', 'http://' . $_SERVER['HTTP_HOST'] . '/' );
                  }
                '';
                STATIC_DEPLOY_PAGE_CACHE_DEFAULT_CACHE_CONTROL = "max-age=6";
              };
            };
          wpPluginCheck = fetchurl {
            url = "https://downloads.wordpress.org/plugin/plugin-check.1.7.0.zip";
            hash = "sha256-6yh3an2AZxyR6ytO0UPMVhISyAHOx4nySACDhU162So=";
          };
          wpInstaller =
            dbHost: dbUser: dataDir:
            writeShellApplication {
              name = "wordpress-installer";
              runtimeInputs = [
                update-wordpress
                wp-cli
              ];
              text = ''
                set -eu
                mkdir -p ${dataDir}
                chmod ug+w ${dataDir}/wp-config.php || true
                cp "${wpConfig dbHost dbUser}" "${dataDir}/wp-config.php"
                update-wordpress ${dataDir} ${wordpress}
                cd ${dataDir}
                wp core install --url="https://example.com" --title=WordPress --admin_user=user --admin_email="user@example.com" --admin_password=pass
                wp option update permalink_structure "/%postname%/"
                rm -rf "./wp-content/plugins/plugin-check" "./wp-content/plugins/staticweb-deploy" "./wp-content/plugins/static-deploy"
                wp plugin install --activate ${staticDeploy}/staticweb-deploy.zip
                wp plugin install --activate ${wpPluginCheck}
              '';
            };
          localstackImage = "docker.io/localstack/localstack:4.9.2";
        in
        with finalPkgs;
        {
          # `process-compose.foo` will add a flake package output called "foo".
          # Therefore, this will add a default package that you can build using
          # `nix build` and run using `nix run`.
          process-compose."default" =
            { config, ... }:
            {
              imports = [ inputs.services-flake.processComposeModules.default ];
              services.memcached."memcached1" = {
                enable = true;
                port = memcachedConfig.port;
                startArgs = [
                  "--memory-limit=${toString memcachedConfig.maxMemory}M"
                  "--port=${toString memcachedConfig.port}"
                ];
              };
              services.mysql."mysql1" = {
                enable = true;
                ensureUsers = [
                  {
                    name = dbUserName;
                    password = dbUserPass;
                    ensurePermissions = {
                      "${dbName}.*" = "ALL PRIVILEGES";
                    };
                  }
                ];
                initialDatabases = [ { name = dbName; } ];
                settings = {
                  mysqld = {
                    bind-address = "127.0.0.1";
                    port = dbPort;
                    tmpdir = "/tmp";
                  };
                };
              };
              services.nginx."nginx1" = {
                enable = true;
                httpConfig = nginxHttpConfig "./data/wordpress1" "${
                  config.services.phpfpm."phpfpm1".dataDir
                }/phpfpm.sock";
              };
              services.phpfpm."phpfpm1" = {
                enable = true;
                listen = "phpfpm.sock";
                extraConfig = {
                  "catch_workers_output" = "yes";
                  "pm" = "ondemand";
                  "pm.max_children" = "5";
                };
                package = php;
                phpOptions = phpOptions;
              };
              # An optional service to run localstack if docker is available
              # We can't run docker in nix flake check,
              # so we run AWS tests in the dev environment.
              settings.processes."localstack-image1" = {
                command = "docker pull ${localstackImage}";
              };
              settings.processes."localstack1" = {
                command = "docker run --rm -p 4668:4566 ${localstackImage}";
                depends_on."localstack-image1".condition = "process_completed_successfully";
              };
              settings.processes."nginx1".depends_on."phpfpm1".condition = "process_healthy";
              settings.processes.test =
                let
                  php = config.services.phpfpm."phpfpm1".package;
                in
                {
                  command = pkgs.writeShellApplication {
                    name = "test";
                    runtimeInputs = [
                      config.services.mysql."mysql1".package
                      php
                      phpPackages.composer
                      wp-cli
                    ];
                    text = ''
                      TMPDIR="$(realpath ./tmp)"
                      mkdir -p "$TMPDIR"
                      echo 'SELECT version();' | mysql -h 127.0.0.1 --port="${toString dbPort}" --user="${dbUserName}" --password="${dbUserPass}" "${dbName}"
                      cp -r --no-preserve=mode ${staticDeployPkgs.composerVendor}/. .
                      cp -r ${staticDeployPkgs.pluginDevSrc}/. .
                      composer dump-autoload
                      WORDPRESS_DIR="$(realpath ./data/wordpress1)"
                      export WORDPRESS_DIR
                      php -d sys_temp_dir="$TMPDIR" vendor/bin/phpunit --do-not-cache-result --testsuite Integration
                    '';
                  };
                  depends_on."mysql1-configure".condition = "process_completed_successfully";
                  depends_on."wordpress1".condition = "process_completed_successfully";
                };
              settings.processes."wordpress1" = {
                command = "${
                  wpInstaller "127.0.0.1:${toString dbPort}" dbUserName "./data/wordpress1"
                }/bin/wordpress-installer";
                depends_on."memcached1".condition = "process_healthy";
                depends_on."mysql1-configure".condition = "process_completed";
              };
            };

          devShells.default = pkgs.mkShell {
            buildInputs = [
              fd
              fzf # Used by just --choose
              inputs.hyperfine-flake.packages.${system}.default
              inputs.hyperfine-flake.packages.${system}.scripts
              jq
              just
              nixfmt-rfc-style
              omnix
              parallel
              php
              phpunit
              phpPackages.composer
              shellcheck
              subversion
              watchexec
              wp-cli
            ];
            inputsFrom = [ config.process-compose."default".services.outputs.devShell ];
          };
        };
    };
}
