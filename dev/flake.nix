{
  inputs = {
    nixpkgs.url = "github:nixos/nixpkgs/nixos-25.05";
    flake-parts.url = "github:hercules-ci/flake-parts";
    systems.url = "github:nix-systems/default";
    process-compose-flake.url = "github:Platonic-Systems/process-compose-flake";
    services-flake.url = "github:juspay/services-flake";
    wordpress-flake.url = "github:staticweb-io/wordpress-flake";
    wp2static.url = ./..;
  };
  outputs = inputs:
    inputs.flake-parts.lib.mkFlake { inherit inputs; } {
      systems = import inputs.systems;
      imports = [ inputs.process-compose-flake.flakeModule ];
      perSystem = { self', pkgs, config, lib, system, ... }:
        with pkgs;
        let
          dbName = "wordpress";
          dbPort = 3306;
          dbUserName = "wordpress";
          dbUserPass = "8BVMm2jqDE6iADNyfaVCxoCzr3eBY6Ep";
          serverPort = 8888;
          phpVersions = {
            php81 = php81;
            php82 = php82;
            php83 = php83;
            php84 = php84;
          };
          phpBins = lib.attrsets.mapAttrsToList (name: phpPkg:
            pkgs.writeShellScriptBin name ''${phpPkg}/bin/php "$@"'')
            phpVersions;
          php = php84.buildEnv {
            extensions = { enabled, all }:
              enabled ++ (with all; [ imagick memcached ]);
          };
          wp2staticPkgs = inputs.wp2static.packages.${system};
          wp2static = wp2staticPkgs.plugin;
        in {
          # `process-compose.foo` will add a flake package output called "foo".
          # Therefore, this will add a default package that you can build using
          # `nix build` and run using `nix run`.
          process-compose."default" = { config, ... }: {
            imports = [ inputs.services-flake.processComposeModules.default ];
            services.mysql."mysql1" = {
              enable = true;
              ensureUsers = [{
                name = dbUserName;
                password = dbUserPass;
                ensurePermissions = { "${dbName}.*" = "ALL PRIVILEGES"; };
              }];
              initialDatabases = [{ name = dbName; }];
              package = pkgs.mariadb;
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
              httpConfig = ''
                server {
                  listen ${toString serverPort} default_server;

                  server_name _;

                  root ./data/wordpress1;

                  index index.php index.html index.htm;

                  location / {
                      try_files $uri $uri/ =404;

                      if (!-e $request_filename) {
                          rewrite ^(.+)$ /index.php?q=$1 last;
                      }
                  }

                  location ~ \.php$ {
                    fastcgi_split_path_info ^(.+\.php)(/.+)$;
                    fastcgi_pass unix:${
                      config.services.phpfpm."phpfpm1".dataDir
                    }/phpfpm.sock;
                    include ${pkgs.nginx}/conf/fastcgi.conf;
                  }

                  location ~ /\.ht {
                    deny all;
                  }
                }
              '';
              package = pkgs.nginx;
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
            };
            settings.processes."nginx1".depends_on."phpfpm1".condition =
              "process_healthy";
            settings.processes."phpfpm1".readiness_probe = lib.mkForce {
              exec.command = "env -i ${pkgs.fcgi}/bin/cgi-fcgi -bind -connect ${
                  config.services.phpfpm."phpfpm1".dataDir
                }/phpfpm.sock";
              initial_delay_seconds = 2;
              period_seconds = 10;
              timeout_seconds = 4;
              success_threshold = 1;
              failure_threshold = 5;
            };
            settings.processes.test =
              let php = config.services.phpfpm."phpfpm1".package;
              in {
                command = pkgs.writeShellApplication {
                  name = "test";
                  runtimeInputs =
                    [ config.services.mysql."mysql1".package php wp-cli ];
                  text = ''
                    TMPDIR="$(realpath ./tmp)"
                    mkdir -p "$TMPDIR"
                    echo 'SELECT version();' | mysql -h 127.0.0.1 --port="${
                      toString dbPort
                    }" --user="${dbUserName}" --password="${dbUserPass}" "${dbName}"
                    ${pkgs.rsync}/bin/rsync -a --copy-links ${wp2staticPkgs.composerVendorDev}/. .
                    ${pkgs.rsync}/bin/rsync -a --copy-links ${wp2staticPkgs.wp2staticSrcDev}/. .
                    WORDPRESS_DIR="$(realpath ./data/wordpress1)"
                    export WORDPRESS_DIR
                    ${php}/bin/php -d sys_temp_dir="$TMPDIR" vendor/bin/phpunit --do-not-cache-result ./tests/integration/
                  '';
                };
                depends_on."mysql1-configure".condition =
                  "process_completed_successfully";
                depends_on."wordpress1".condition =
                  "process_completed_successfully";
              };
            settings.processes."wordpress1" = let
              WPConfigFormat =
                (inputs.wordpress-flake.lib.${system}.WPConfigFormat {
                  inherit pkgs lib;
                }).format { };
              update-wordpress =
                inputs.wordpress-flake.packages.${system}.update-wordpress;
              wpConfig = inputs.wordpress-flake.lib.${system}.mkWPConfig {
                inherit pkgs lib;
                name = "wp-config.php";
                settings = {
                  DB_HOST = "127.0.0.1:${toString dbPort}";
                  DB_NAME = dbName;
                  DB_USER = dbUserName;
                  DB_PASSWORD = dbUserPass;
                  WP_AUTO_UPDATE_CORE = false;

                  AUTH_KEY =
                    "A6tr^0=N<QP++W-%/hv1yOZ4]f<3m`/}0(A/UFi6pmy|ZLT)=>e+raWRmgYCs>aK";
                  SECURE_AUTH_KEY =
                    "Vj>>M=2uvzzWw-tqT?]H3RWsG%jTA9EhJKn~F6:8B<So+<A_},Y<RW-U)}/w-0Y+";
                  LOGGED_IN_KEY =
                    "jJCaP}~YG-Se+<WK5g9.@K*^g7*v=_yLyX7+i?{Mc%CcJ|L54u=+*+rW_Uxa{95L";
                  NONCE_KEY =
                    "98.DYg|E,*CV]Rz&#Q{j]?n[!sQji*X9%`Ic_n>NExS<7Sn[SG:`P8)*CqC[G2NF";
                  AUTH_SALT =
                    "*KON9~cuX+lG,Kx6`^5d#kyu5oFt{^~O:[]pB]F745S<B2U*L0aHb;(pEn:kPggf";
                  SECURE_AUTH_SALT =
                    "MV6l72,Yi+y8X`0wm5-T)6T#ZY~Sp;G+e3. ^CHdZ1W_*WY?;9>c}^|:[<j0FkpV";
                  LOGGED_IN_SALT =
                    "Don!4M=(5=Y=*@.NI:bn$V[FZ*a~wyJ:s9p&l@XD{7WzqBDO.3+-#[H>79,rG)Q~";
                  NONCE_SALT =
                    "t={*XeC6q4LZ5:%wo*C3f-sr6g3#Wa}_EMf}Jh$8*P/%4SdK4=0hjjnVa&8yY#-F";
                  WP_CACHE_KEY_SALT =
                    ")O~B@EKC(tfdgDg6R8@6;ePxJJkXMpZ&.u?X{j##:@7-,/*YKvvl-l4}r^@2=Ha-";

                  HTTP_HOST = WPConfigFormat.lib.mkInline ''
                    if ( defined( 'WP_CLI' ) ) {
                        $_SERVER['HTTP_HOST'] = isset( $_ENV['HTTP_HOST'] ) ? $_ENV['HTTP_HOST'] : 'localhost:${
                          toString serverPort
                        }';
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
                };
              };
            in {
              command = ''
                set -eu
                mkdir -p ./data/wordpress1
                chmod ug+w ./data/wordpress1/wp-config.php || true
                cp "${wpConfig}" "./data/wordpress1/wp-config.php"
                ${update-wordpress}/bin/update-wordpress ./data/wordpress1
                cd ./data/wordpress1
                ${pkgs.wp-cli}/bin/wp core install --url="https://example.com" --title=WordPress --admin_user=user --admin_email="user@example.com" --admin_password=pass
                ${pkgs.wp-cli}/bin/wp option update permalink_structure "/%postname%/"
                ${pkgs.wp-cli}/bin/wp plugin install --activate ${wp2static}/wp2static.zip
              '';
              depends_on."mysql1-configure".condition = "process_completed";
            };
          };

          devShells.default = pkgs.mkShell {
            buildInputs =
              [ omnix php phpunit phpPackages.composer shellcheck wp-cli ]
              ++ phpBins;
            inputsFrom =
              [ config.process-compose."default".services.outputs.devShell ];
          };
        };
    };
}
