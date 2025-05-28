{
  description = "WP2Static";

  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixos-25.05";
    flake-utils.url = "github:numtide/flake-utils";
  };

  outputs = { self, nixpkgs, flake-utils, ... }:
    flake-utils.lib.eachDefaultSystem (system:
      with import nixpkgs { inherit system; };
      with pkgs;
      let
        phpVersions = {
          php81 = php81;
          php82 = php82;
          php83 = php83;
          php84 = php84;
        };
        phpBins = lib.attrsets.mapAttrsToList (name: phpPkg:
          pkgs.writeShellScriptBin name ''${phpPkg}/bin/php "$@"'') phpVersions;
      in {
        devShells.default = mkShell {
          buildInputs = [ php phpPackages.composer shellcheck zip ] ++ phpBins;
        };
      });
}
