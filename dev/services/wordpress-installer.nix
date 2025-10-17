{
  config,
  lib,
  pkgs,
  ...
}:
let

  cfg = config.services.wordpress-installer;

in
{
  options = {

    services.wordpress-installer = {
      enable = lib.mkEnableOption "WordPress Installer";

      package = lib.mkOption {
        type = lib.types.package;
        description = "Which WordPress Installer derivation to use.";
      };

      user = lib.mkOption {
        type = lib.types.str;
        description = "The user to run WordPress Installer as";
      };
    };

  };

  config = lib.mkIf config.services.wordpress-installer.enable {
    systemd.services.wordpress-installer = {
      description = "WordPress Installer";

      wantedBy = [ "multi-user.target" ];
      after = [ "network.target" ];

      serviceConfig = {
        ExecStart = "${cfg.package}/bin/wordpress-installer";
        Type = "oneshot";
        User = cfg.user;
      };
    };
  };

}
