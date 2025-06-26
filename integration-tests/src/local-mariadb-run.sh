#!@bash@/bin/bash

set -euo pipefail

DATA_DIR="$1"

@mariadb@/bin/mysqld --defaults-file="@cnf-file@" -h "${DATA_DIR:?}" &
MYSQLD_PID=$!

shutdown() {
  echo "Shutting down mysqld..."
  @mariadb@/bin/mysqladmin --socket="${DATA_DIR:?}/mysql.sock" shutdown
}

trap shutdown EXIT SIGINT SIGTERM

wait $MYSQLD_PID
