{
  inputs = {
    nixpkgs.url = "github:nixos/nixpkgs/nixos-25.05";
    flake-parts.url = "github:hercules-ci/flake-parts";
    systems.url = "github:nix-systems/default";
    hyperfine-flake = {
      inputs.nixpkgs.follows = "nixpkgs";
      url = "github:john-shaffer/hyperfine-flake";
    };
    microvm = {
      url = "github:microvm-nix/microvm.nix";
      inputs.nixpkgs.follows = "nixpkgs";
    };
    process-compose-flake.url = "github:Platonic-Systems/process-compose-flake";
    services-flake.url = "github:juspay/services-flake";
    wordpress-flake.url = "github:staticweb-io/wordpress-flake";
    static-deploy.url = ./..;
  };
  outputs = inputs:
    let
      dbName = "wordpress";
      dbPort = 3306;
      dbUserName = "wordpress";
      dbUserPass = "8BVMm2jqDE6iADNyfaVCxoCzr3eBY6Ep";
      serverPort = 8888;
      memcachedConfig = {
        enable = true;
        maxMemory = 100;
      };
      mysqlConfig = {
        enable = true;
        ensureUsers = [{
          name = dbUserName;
          ensurePermissions = { "${dbName}.*" = "ALL PRIVILEGES"; };
        }];
        initialDatabases = [{ name = dbName; }];
      };
      # Note that /tmp/xd has to be created to receive traces
      phpOptions = ''
        opcache.interned_strings_buffer = 16
        opcache.jit = 1255
        opcache.jit_buffer_size = 8M
        upload_max_filesize=1024M
      '';
      phpfpmConfig = {
        pools = {
          default = {
            settings = {
              "catch_workers_output" = "yes";
              "pm" = "ondemand";
              "pm.max_children" = "5";
            };
            group = "php";
            user = "php";
          };
        };
        phpOptions = phpOptions;
      };
      nixosModules = {
        wordpress-server = {
          services.memcached = memcachedConfig;
          services.mysql = mysqlConfig;
          services.phpfpm = phpfpmConfig;
          users.users.php = {
            isSystemUser = true;
            group = "php";
          };
          users.groups.php = { };
        };
      };
    in inputs.flake-parts.lib.mkFlake { inherit inputs; } {
      systems = import inputs.systems;
      imports = [ inputs.process-compose-flake.flakeModule ];
      perSystem = { self', pkgs, config, lib, system, ... }:
        let
          getEnv = name: default:
            (if "" == builtins.getEnv name then
              default
            else
              builtins.getEnv name);
          phpPackage = getEnv "PHP_PACKAGE" "php";
          wordpressPackage = getEnv "WORDPRESS_PACKAGE" "default";
          staticDeployLib = inputs.static-deploy.lib.${system};
          staticDeployPkgs = inputs.static-deploy.packages.${system};
          staticDeploy = staticDeployPkgs.plugin;
          # Note that /tmp/xd has to be created to receive traces
          phpOptions = ''
            opcache.interned_strings_buffer = 16
            opcache.jit = 1255
            opcache.jit_buffer_size = 8M
            upload_max_filesize=1024M
            xdebug.mode=trace
            xdebug.output_dir=/tmp/xd
            xdebug.start_with_request=trigger
            xdebug.trace_format=3
            xdebug.trace_output_name = xdebug.trace.%t.%s
            xdebug.trigger_value = "e5c2217a39ff4e9ad4c5f99243bb47de68ee112aa685f79264686b202591ec80"
          '';
          overlay = self: super:
            let
              php = super.${phpPackage}.buildEnv {
                extraConfig = phpOptions;
                extensions = { enabled, all }:
                  enabled ++ (with all; [ apcu imagick memcached xdebug ]);
              };
              phpIniFile =
                pkgs.runCommand "php.ini" { preferLocalBuild = true; } ''
                  cat ${php}/etc/php.ini > $out
                '';
              wp-cli = super.wp-cli.override { phpIniFile = phpIniFile; };
            in { inherit php wp-cli; };
          finalPkgs = import pkgs.path {
            inherit (pkgs) system;
            overlays = [ overlay ];
          };
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
            (inputs.wordpress-flake.lib.${system}.WPConfigFormat {
              inherit pkgs lib;
            }).format { };
          update-wordpress =
            inputs.wordpress-flake.packages.${system}.update-wordpress;
          wordpress =
            inputs.wordpress-flake.packages.${system}.${wordpressPackage};
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

              WP_CACHE = true;
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
              STATIC_DEPLOY_PAGE_CACHE_DEFAULT_CACHE_CONTROL = "max-age=6";
            };
          };
          wpInstaller = dataDir:
            pkgs.writeShellApplication {
              name = "wordpress-installer";
              text = ''
                set -eu
                mkdir -p ${dataDir}
                chmod ug+w ${dataDir}/wp-config.php || true
                cp "${wpConfig}" "${dataDir}/wp-config.php"
                ${update-wordpress}/bin/update-wordpress ${dataDir} ${wordpress}
                cd ${dataDir}
                ${pkgs.wp-cli}/bin/wp core install --url="https://example.com" --title=WordPress --admin_user=user --admin_email="user@example.com" --admin_password=pass
                ${pkgs.wp-cli}/bin/wp option update permalink_structure "/%postname%/"
                rm -rf "./wp-content/plugins/static-deploy"
                ${pkgs.wp-cli}/bin/wp plugin install --activate ${staticDeploy}/static-deploy.zip
              '';
            };
          wordpress-firecracker = inputs.nixpkgs.lib.nixosSystem {
            inherit system;
            pkgs = finalPkgs;
            modules = with finalPkgs; [
              inputs.microvm.nixosModules.microvm
              nixosModules.wordpress-server
              ({ config, ... }: {
                environment.systemPackages = [
                  mariadb
                  memcached
                  nginx
                  php
                ];
                services.mysql.package = mariadb;
                services.nginx = {
                  enable = true;
                  httpConfig = nginxHttpConfig "/var/wordpress"
                    config.services.phpfpm.pools.default.socket;
                };
              })
              {
                networking.hostName = "wordpress-firecracker";
                users.users.root.password = "";
                microvm = {
                  hypervisor = "firecracker";
                  socket = "control.socket";
                  volumes = [{
                    mountPoint = "/var";
                    image = "var.img";
                    size = 8096;
                  }];
                };
              }
            ];
          };
        in with finalPkgs; {
          # `process-compose.foo` will add a flake package output called "foo".
          # Therefore, this will add a default package that you can build using
          # `nix build` and run using `nix run`.
          process-compose."default" = { config, ... }: {
            imports = [ inputs.services-flake.processComposeModules.default ];
            services.memcached."memcached1" = {
              enable = true;
              startArgs =
                [ "--memory-limit=${toString memcachedConfig.maxMemory}M" ];
            };
            services.mysql."mysql1" = {
              enable = true;
              ensureUsers = [{
                name = dbUserName;
                password = dbUserPass;
                ensurePermissions = { "${dbName}.*" = "ALL PRIVILEGES"; };
              }];
              initialDatabases = [{ name = dbName; }];
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
              httpConfig = nginxHttpConfig "./data/wordpress1"
                "${config.services.phpfpm."phpfpm1".dataDir}/phpfpm.sock";
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
              command = "docker pull docker.io/localstack/localstack:4.6.0";
            };
            settings.processes."localstack1" = {
              command =
                "docker run --rm docker.io/localstack/localstack:4.6.0 -p 4566:4566";
              depends_on."localstack-image1".condition =
                "process_completed_successfully";
            };
            settings.processes."nginx1".depends_on."phpfpm1".condition =
              "process_healthy";
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
                    ${pkgs.rsync}/bin/rsync -a --copy-links ${staticDeployPkgs.composerVendorDev}/. .
                    ${pkgs.rsync}/bin/rsync -a --copy-links ${staticDeployLib.staticDeploySrcDev}/. .
                    chmod ug+w -R ./vendor
                    ${phpPackages.composer}/bin/composer dump-autoload
                    WORDPRESS_DIR="$(realpath ./data/wordpress1)"
                    export WORDPRESS_DIR
                    ${php}/bin/php -d sys_temp_dir="$TMPDIR" vendor/bin/phpunit --do-not-cache-result --testsuite Integration
                  '';
                };
                depends_on."mysql1-configure".condition =
                  "process_completed_successfully";
                depends_on."wordpress1".condition =
                  "process_completed_successfully";
              };
            settings.processes."wordpress1" = {
              command =
                "${wpInstaller "./data/wordpress1"}/bin/wordpress-installer";
              depends_on."memcached1".condition = "process_healthy";
              depends_on."mysql1-configure".condition = "process_completed";
            };
          };

          devShells.default = pkgs.mkShell {
            buildInputs = [
              fd
              inputs.hyperfine-flake.packages.${system}.default
              inputs.hyperfine-flake.packages.${system}.scripts
              jq
              inputs.microvm.packages.${system}.microvm
              omnix
              php
              phpunit
              phpPackages.composer
              shellcheck
              wp-cli
            ];
            inputsFrom =
              [ config.process-compose."default".services.outputs.devShell ];
          };
          packages = {
            wordpress-firecracker =
              wordpress-firecracker.config.microvm.declaredRunner;
          };
        };
    } // {
      inherit nixosModules;
    };
}
