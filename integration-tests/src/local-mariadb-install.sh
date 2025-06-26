#!@bash@/bin/bash

set -euo pipefail

@mariadb@/bin/mysql_install_db --defaults-file="@cnf-file@" --datadir="$1"
