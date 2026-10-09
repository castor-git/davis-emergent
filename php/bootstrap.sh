#!/usr/bin/env bash
# Bootstrap script: reinstalls PHP + MariaDB (lost on pod restart because they
# live in /usr/*), initializes the DB if needed. Idempotent.
set -e
LOG=/var/log/davisporn-bootstrap.log
exec >>"$LOG" 2>&1
# Serialize concurrent invocations (mariadb + php-app start at the same time)
exec 9>/var/lock/davisporn-bootstrap.lock
flock -w 600 9
echo "[$(date -u)] bootstrap start"

# Install packages if binaries/extensions are missing
NEED_PKGS=""
php_ok() { command -v php >/dev/null 2>&1 && php -m 2>/dev/null | grep -qi '^pdo_mysql$' && php -m 2>/dev/null | grep -qi '^curl$' && php -m 2>/dev/null | grep -qi '^mbstring$'; }
php_ok                                || NEED_PKGS="$NEED_PKGS php-cli php-mysql php-mbstring php-xml php-curl php-zip"
command -v mariadbd >/dev/null 2>&1   || NEED_PKGS="$NEED_PKGS mariadb-server"
if [ -n "$NEED_PKGS" ]; then
  echo "installing:$NEED_PKGS"
  # Another apt (platform init) may hold the dpkg lock for minutes: wait for it and retry instead of dying
  APT_OPTS="-o DPkg::Lock::Timeout=900 -y -qq"
  for attempt in $(seq 1 30); do
    if apt-get $APT_OPTS update && DEBIAN_FRONTEND=noninteractive apt-get $APT_OPTS install --no-install-recommends $NEED_PKGS; then
      break
    fi
    echo "apt attempt $attempt failed, retrying in 10s"; sleep 10
  done
  php_ok || { echo "PHP install incomplete"; exit 1; }
fi

# Init MariaDB data dir if empty (persistent datadir lives under /app/mysql)
mkdir -p /var/run/mysqld /app/mysql
chown -R mysql:mysql /var/run/mysqld /app/mysql
if [ ! -d /app/mysql/mysql ]; then
  echo "installing db"
  mariadb-install-db --user=mysql --datadir=/app/mysql --auth-root-authentication-method=normal >/dev/null
fi
echo "[$(date -u)] bootstrap done"
