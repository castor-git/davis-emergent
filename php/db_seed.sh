#!/usr/bin/env bash
# Waits for MariaDB to accept root logins, then ensures the app DB and user exist.
set -e
for i in $(seq 1 60); do
  if mariadb -uroot -e "SELECT 1" >/dev/null 2>&1; then break; fi
  sleep 1
done
mariadb -uroot -e "CREATE DATABASE IF NOT EXISTS davisporn CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'dav'@'localhost' IDENTIFIED BY 'davpass';
GRANT ALL ON davisporn.* TO 'dav'@'localhost';
FLUSH PRIVILEGES;"
