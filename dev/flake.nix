{
  inputs = {
    nixpkgs.url = "github:nixos/nixpkgs/nixos-25.05";
    flake-parts.url = "github:hercules-ci/flake-parts";
    systems.url = "github:nix-systems/default";
    process-compose-flake.url = "github:Platonic-Systems/process-compose-flake";
    services-flake.url = "github:juspay/services-flake";
  };
  outputs = inputs:
    inputs.flake-parts.lib.mkFlake { inherit inputs; } {
      systems = import inputs.systems;
      imports = [ inputs.process-compose-flake.flakeModule ];
      perSystem = { self', pkgs, config, lib, ... }: {
        # `process-compose.foo` will add a flake package output called "foo".
        # Therefore, this will add a default package that you can build using
        # `nix build` and run using `nix run`.
        process-compose."default" = { config, ... }:
          let
            dbName = "wordpress";
            dbPort = 3306;
            dbUserName = "wordpress";
            dbUserPass = "8BVMm2jqDE6iADNyfaVCxoCzr3eBY6Ep";
            php = pkgs.php84.buildEnv {
              extensions = { enabled, all }:
                enabled ++ (with all; [ imagick memcached ]);
            };
          in {
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
                };
              };
            };
            services.nginx."nginx1" = {
              enable = true;
              httpConfig = ''
                server {
                  listen 8888 default_server;

                  server_name _;

                  root ${config.services.phpfpm."phpfpm1".dataDir}/www;

                  index index.php index.html index.htm;

                  location / {
                      try_files $uri $uri/ =404;

                      if (!-e $request_filename) {
                          rewrite ^(.+)$ /index.php?q=$1 last;
                      }
                  }

                  location ~ \.php$ {
                    fastcgi_split_path_info ^(.+\.php)(/.+)$;
                    fastcgi_pass unix:${config.services.phpfpm."phpfpm1".dataDir}/phpfpm.sock;
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
            settings.processes.test = {
              command = pkgs.writeShellApplication {
                name = "test";
                runtimeInputs = [ config.services.mysql.mysql1.package ];
                text = ''
                  echo 'SELECT version();' | mysql -h 127.0.0.1 --port="${
                    toString dbPort
                  }" --user="${dbUserName}" --password="${dbUserPass}" "${dbName}"
                '';
              };
              depends_on."mysql1-configure".condition = "process_completed";
            };
          };

        devShells.default = pkgs.mkShell {
          inputsFrom =
            [ config.process-compose."default".services.outputs.devShell ];
        };
      };
    };
}
