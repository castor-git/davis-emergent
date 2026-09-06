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
# Wait for any foreign apt/dpkg process to release its lock
for _ in $(seq 1 120); do
  if fuser /var/lib/dpkg/lock-frontend /var/lib/apt/lists/lock >/dev/null 2>&1; then sleep 5; else break; fi
done

# Install packages if binaries missing
NEED_PKGS=""
command -v php >/dev/null 2>&1        || NEED_PKGS="$NEED_PKGS php-cli php-mysql php-mbstring php-xml php-curl php-zip"
command -v mariadbd >/dev/null 2>&1   || NEED_PKGS="$NEED_PKGS mariadb-server"
if [ -n "$NEED_PKGS" ]; then
  echo "installing:$NEED_PKGS"
  apt-get update -qq
  DEBIAN_FRONTEND=noninteractive apt-get install -y -qq --no-install-recommends $NEED_PKGS
fi

# Init MariaDB data dir if empty (persistent datadir lives under /app/mysql)
mkdir -p /var/run/mysqld /app/mysql
chown -R mysql:mysql /var/run/mysqld /app/mysql
if [ ! -d /app/mysql/mysql ]; then
  echo "installing db"
  mariadb-install-db --user=mysql --datadir=/app/mysql --auth-root-authentication-method=normal >/dev/null
fi
echo "[$(date -u)] bootstrap done"
