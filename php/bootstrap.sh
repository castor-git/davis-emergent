#!/usr/bin/env bash
# Bootstrap script: reinstalls PHP + MariaDB (lost on pod restart because they
# live in /usr/*), initializes the DB if needed. Idempotent.
set -e
LOG=/var/log/davisporn-bootstrap.log
exec >>"$LOG" 2>&1
echo "[$(date -u)] bootstrap start"

# Install packages if binaries missing
NEED_PKGS=""
command -v php >/dev/null 2>&1        || NEED_PKGS="$NEED_PKGS php-cli php-mysql php-mbstring php-xml php-curl php-zip"
command -v mariadbd >/dev/null 2>&1   || NEED_PKGS="$NEED_PKGS mariadb-server"
if [ -n "$NEED_PKGS" ]; then
  echo "installing:$NEED_PKGS"
  apt-get update -qq
  DEBIAN_FRONTEND=noninteractive apt-get install -y -qq --no-install-recommends $NEED_PKGS
fi

# Init MariaDB data dir if empty
mkdir -p /var/run/mysqld /var/lib/mysql
chown -R mysql:mysql /var/run/mysqld /var/lib/mysql
if [ ! -d /var/lib/mysql/mysql ]; then
  echo "installing db"
  mariadb-install-db --user=mysql --datadir=/var/lib/mysql --auth-root-authentication-method=normal >/dev/null
fi
echo "[$(date -u)] bootstrap done"
