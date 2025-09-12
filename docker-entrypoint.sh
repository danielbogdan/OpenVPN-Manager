#!/usr/bin/env bash
set -e

# Potrivește GID cu socketul Docker și adaugă www-data în grupul corect
if [ -S /var/run/docker.sock ]; then
  SOCK_GID=$(stat -c '%g' /var/run/docker.sock || true)
  if [ "$SOCK_GID" = "0" ]; then
    usermod -aG root www-data
  else
    getent group docker >/dev/null 2>&1 || groupadd -g "$SOCK_GID" docker || true
    usermod -aG docker www-data || true
  fi
else
  echo "[entrypoint] ATENȚIE: /var/run/docker.sock NU este montat în container"
fi

# Setează permisiuni minimale pe storage (dacă ai nevoie)
chown -R www-data:www-data /var/www/html || true

# mică diagnoză în logurile Apache la pornire
echo "[entrypoint] docker path: $(command -v docker || echo 'not found')"
echo "[entrypoint] whoami: $(whoami), id: $(id)"

# Pornește Apache
exec apache2-foreground
