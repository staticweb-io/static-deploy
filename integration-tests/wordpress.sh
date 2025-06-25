#!/usr/bin/env bash

set -euo pipefail

TMPDIR=$(mktemp -d)
rsync -a --copy-links --no-perms --no-owner --no-group "$WORDPRESS_PATH"/share/wordpress "$TMPDIR"
chown -R "$USER:$USER" "$TMPDIR"
chattr -R -i -a "$TMPDIR"
chmod ugo+w -R "$TMPDIR"
rm -rf wordpress
mv -f "$TMPDIR"/wordpress .
rmdir "$TMPDIR"

while ! test -S "mariadb/data/mysql.sock"; do
  sleep 1
done

mysql --socket=mariadb/data/mysql.sock -e "CREATE USER IF NOT EXISTS 'wordpress'@'localhost' IDENTIFIED BY '8BVMm2jqDE6iADNyfaVCxoCzr3eBY6Ep'; CREATE DATABASE IF NOT EXISTS wordpress; GRANT ALL PRIVILEGES ON wordpress.* TO 'wordpress'@'localhost'; FLUSH PRIVILEGES;"

cp wp-config.php wordpress
wp core install --path=wordpress --url="https://example.com" --title=WordPress --admin_user=user --admin_email="user@example.com" --admin_password=pass
wp option update permalink_structure "/%postname%/" --path=wordpress
# `wp plugin uninstall` doesn't successfuly remove the plugins,
# so we deactivate them and delete them ourselves.
wp plugin deactivate --path=wordpress --all
rm -r wordpress/wp-content/plugins/*/
rm -r wordpress/wp-content/plugins/hello.php
