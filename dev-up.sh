#!/usr/bin/env bash
# dev-up.sh — levanta la pila del Codespace (o la repara) y dice en UNA pantalla si esta lista.
#
#   ./dev-up.sh            arrancar / comprobar (seguro: no borra nada)
#   ./dev-up.sh --repair   almacen de Docker roto ("content digest … not found",
#                          "RWLayer … is unexpectedly nil"): borra contenedores e IMAGENES y
#                          reconstruye. Los VOLUMENES (base de datos, vendor, node_modules,
#                          fotos) se conservan.
#
# Nacio el 2026-11-12: tras un corte de internet hacian falta los mismos cinco comandos de
# siempre, en orden, y cada fallo del entorno (Docker roto, puerto 8000 privado, base sin
# sembrar) se diagnosticaba a mano. Hace en orden:
#   1 Docker vivo · 2 disco · 3 docker compose up · 4 esperar a la API ·
#   5 mvp-init.py (siembra si hace falta y pone el 8000 en Public) · 6 resumen con lo que falta.
# Antes de cerrar el Codespace:  docker compose stop
set -u
cd "$(dirname "$0")"

REPAIR=0
[ "${1:-}" = "--repair" ] && REPAIR=1
FAIL=0
ok()   { printf '  \033[32m✓\033[0m %s\n' "$*"; }
bad()  { printf '  \033[31m✗\033[0m %s\n' "$*"; FAIL=1; }
step() { printf '\n\033[1m%s\033[0m\n' "$*"; }
code() { curl -s -o /dev/null -m 5 -w '%{http_code}' "$@" 2>/dev/null || true; }

step "1) Docker"
if ! docker info >/dev/null 2>&1; then
  bad "el demonio de Docker no responde"
  echo "     En el Codespace: Ctrl+Shift+P → «Codespaces: Rebuild Container», y repite ./dev-up.sh"
  exit 1
fi
ok "Docker responde"

step "2) Disco"
# 2026-11-12: el disco lleno ERA la causa de los "RWLayer … nil" / "content digest … not
# found" de tres dias: Docker no puede escribir sus capas y deja el almacen a medias. Con
# el disco asi, levantar o reparar la pila solo vuelve a llenarlo — por eso aqui se PARA.
USE=$(df --output=pcent / 2>/dev/null | tail -1 | tr -dc '0-9')
FREE=$(df -h --output=avail / 2>/dev/null | tail -1 | tr -d ' ')
if [ -n "$USE" ] && [ "$USE" -ge 90 ]; then
  bad "disco al ${USE}% (libres: ${FREE:-?}). Con el disco lleno Docker rompe su almacen y Postgres no arranca."
  echo "     Que ocupa el sitio:"
  docker system df 2>/dev/null | sed 's/^/       /'
  echo "     Liberar sin perder datos (cache de compilacion, cache de npm, imagenes sin usar):"
  echo "       docker builder prune -af && docker image prune -af && npm cache clean --force"
  echo "     y repite ./dev-up.sh. Si no baja del 90%: Codespaces → «Change machine type» (mas disco)."
  exit 1
fi
ok "disco al ${USE:-?}% (libres: ${FREE:-?})"

step "3) Pila (docker compose)"
if [ "$REPAIR" = 1 ]; then
  echo "  --repair: bajando la pila y borrando imagenes rotas (los volumenes se quedan)…"
  docker compose down
  docker system prune -af
  OUT=$(docker compose up -d --build 2>&1); RC=$?
else
  OUT=$(docker compose up -d 2>&1); RC=$?
fi
echo "$OUT" | tail -4 | sed 's/^/     /'
if [ "$RC" -ne 0 ]; then
  if echo "$OUT" | grep -qE 'content digest|RWLayer|failed to extract layer|apply layer error'; then
    bad "el almacen de Docker esta roto (no es el codigo). Arreglo:  ./dev-up.sh --repair"
  else
    bad "docker compose up fallo (salida arriba)"
  fi
  exit 1
fi
ok "docker compose up -d"

step "4) Esperando a la API (hasta 90 s)"
API=000
for _ in $(seq 1 45); do
  API=$(code -X POST localhost:8000/api/login -H 'Accept: application/json')
  [ "$API" = "422" ] && break
  sleep 2
done
if [ "$API" = "422" ]; then ok "API viva en :8000"; else bad "la API no contesta (ultimo codigo: $API) — docker compose logs --tail=30 backend nginx"; fi

step "5) Base de datos y puerto 8000 (mvp-init.py)"
if [ "$API" = "422" ]; then
  python3 mvp-init.py || bad "mvp-init.py fallo (salida arriba)"
else
  echo "     (se salta: sin API no hay nada que sembrar)"
fi

step "6) Resumen"
RUNNING=" $(docker compose ps --services --filter status=running 2>/dev/null | sort | tr '\n' ' ')"
for s in db backend nginx frontend landing; do
  case "$RUNNING" in
    *" $s "*) ok "servicio $s" ;;
    *) bad "servicio $s caido — docker compose logs --tail=30 $s" ;;
  esac
done
# El SPA y la landing compilan al arrancar: pueden tardar un par de minutos tras un up.
for p in 4200 4300; do
  C=000
  for _ in $(seq 1 60); do C=$(code "localhost:$p"); [ "$C" = "200" ] && break; sleep 3; done
  if [ "$C" = "200" ]; then ok "http://localhost:$p responde"; else bad "localhost:$p no responde (aun compilando? docker compose logs --tail=30 $([ $p = 4200 ] && echo frontend || echo landing))"; fi
done

echo
if [ "$FAIL" = 0 ]; then
  printf '\033[32mLISTO.\033[0m App: puerto 4200 · Landing: 4300 · Tablero: cd design && python3 -m http.server 8080\n'
  echo "Usuarios: client1@pubads.test, provider1@pubads.test, … contrasena 'password'."
else
  printf '\033[31mHAY PROBLEMAS\033[0m (los ✗ de arriba dicen que hacer).\n'
  exit 1
fi
