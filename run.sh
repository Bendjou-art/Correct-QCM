#!/usr/bin/env bash
# ============================================================
#  Correct-QCM — Lancement synchronisé des trois services
#  Usage:
#    ./run.sh            -> démarre OCR (8000) + Web (8080)
#    ./run.sh mobile     -> démarre en plus l'app Flutter
#    ./run.sh stop       -> arrête les services démarrés ici
# ============================================================
set -uo pipefail
# NB : on n'utilise PAS `set -e` volontairement — une dépendance manquante
# (composer, tesseract, gd/curl…) ne doit pas tuer le script entier et
# laisser l'autre service orphelin.

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WEB_PORT="${WEB_PORT:-8080}"
OCR_PORT="${OCR_PORT:-8000}"
LOG_DIR="$ROOT/.run-logs"
PID_DIR="$ROOT/.run-pids"

# composer peut être installé localement (sans sudo) dans ~/.local/bin
export PATH="$HOME/.local/bin:$PATH"

mkdir -p "$LOG_DIR" "$PID_DIR"

c_ok()   { printf '\033[32m%s\033[0m\n' "$1"; }
c_info() { printf '\033[36m%s\033[0m\n' "$1"; }
c_err()  { printf '\033[31m%s\033[0m\n' "$1"; }
c_warn() { printf '\033[33m%s\033[0m\n' "$1"; }

stop_services() {
  c_info "Arrêt des services…"
  for pidfile in "$PID_DIR"/*.pid; do
    [ -e "$pidfile" ] || continue
    pid="$(cat "$pidfile")"
    if kill -0 "$pid" 2>/dev/null; then
      kill "$pid" 2>/dev/null || true
      echo "  arrêté PID $pid ($(basename "$pidfile" .pid))"
    fi
    rm -f "$pidfile"
  done
  pkill -f "uvicorn main:app" 2>/dev/null || true
  pkill -f "php -S .*:$WEB_PORT" 2>/dev/null || true
  c_ok "Services arrêtés."
}

start_bg() {
  # $1 = nom, $2 = pidfile, $3 = logfile, reste = commande
  local name="$1" pidfile="$2" logfile="$3"; shift 3
  "$@" >"$logfile" 2>&1 &
  echo $! > "$pidfile"
  c_ok "$name démarré (PID $!) — log: $logfile"
}

check_port() {
  local port="$1" name="$2"
  if ss -ltn 2>/dev/null | grep -q ":$port "; then
    c_err "Le port $port est déjà utilisé — $name ne peut pas démarrer."
    return 1
  fi
  return 0
}

# ---------- OCR service (Python / FastAPI) ----------
start_ocr() {
  c_info "== Service OCR =="
  cd "$ROOT/ocr-service"

  # Créer le venv s'il n'existe pas…
  if [ ! -d venv ]; then
    c_info "Création de l'environnement virtuel Python…"
    python3 -m venv venv || { c_err "Échec de création du venv."; return 1; }
  fi

  # …ET vérifier réellement que les dépendances sont là (un dossier venv
  # vide ne veut PAS dire que uvicorn est installé).
  if [ ! -x ./venv/bin/uvicorn ] || ! ./venv/bin/python -c 'import fastapi, cv2, numpy, PIL, pytesseract' 2>/dev/null; then
    c_warn "Dépendances Python manquantes — pip install -r requirements.txt…"
    ./venv/bin/pip install --quiet --upgrade pip || true
    if ! ./venv/bin/pip install -r requirements.txt; then
      c_err "Échec de l'installation des dépendances Python (voir ci-dessus)."
      return 1
    fi
  fi

  if ! command -v tesseract >/dev/null 2>&1; then
    c_warn "tesseract introuvable : la lecture du matricule (OCR) échouera."
    c_warn "  Installe-le : sudo apt install tesseract-ocr tesseract-ocr-fra"
  fi

  check_port "$OCR_PORT" "ocr-service" || return 1
  start_bg "ocr-service" "$PID_DIR/ocr.pid" "$LOG_DIR/ocr.log" \
    ./venv/bin/uvicorn main:app --host 0.0.0.0 --port "$OCR_PORT"
}

# ---------- Web app (PHP) ----------
start_web() {
  c_info "== Application web (PHP) =="
  cd "$ROOT/web"

  # Composer n'est pas toujours installé : on le signale clairement au lieu
  # de laisser « composer: commande introuvable » tuer le script.
  if [ ! -f vendor/autoload.php ]; then
    if command -v composer >/dev/null 2>&1; then
      c_info "composer install…"
      composer install --no-interaction || c_warn "composer install a échoué (PDF/Excel indisponibles)."
    else
      c_warn "composer introuvable — dépendances PHP (TCPDF, PhpSpreadsheet) non installées."
      c_warn "  Installe-le : sudo apt install composer"
      c_warn "  ou sans sudo : php -r \"copy('https://getcomposer.org/installer','/tmp/ci.php');\" \\"
      c_warn "                && php /tmp/ci.php --install-dir=\$HOME/.local/bin --filename=composer"
    fi
  fi

  if [ ! -f config/database.php ]; then
    if [ -f config/database.example.php ]; then
      cp config/database.example.php config/database.php
      c_err "config/database.php créé depuis l'exemple → renseigne tes identifiants DB."
    else
      c_err "config/database.php absent et aucun database.example.php trouvé."
      c_err "  Toutes les pages PHP échoueront tant qu'il manque."
    fi
  fi

  check_port "$WEB_PORT" "web" || return 1
  start_bg "web" "$PID_DIR/web.pid" "$LOG_DIR/web.log" \
    php -S "0.0.0.0:$WEB_PORT" -t "$ROOT/web"
}

# ---------- Mobile (Flutter) ----------
start_mobile() {
  c_info "== Application mobile (Flutter) =="
  cd "$ROOT/mobile"
  flutter pub get
  c_info "Lancement Flutter (appareil/émulateur requis)…"
  flutter run
}

print_summary() {
  echo
  c_ok "Correct-QCM est synchronisé :"
  echo "  • Web       : http://localhost:$WEB_PORT"
  echo "  • OCR API   : http://localhost:$OCR_PORT/docs"
  echo "  • Logs      : $LOG_DIR"
  echo
  echo "  Arrêt : ./run.sh stop"
  echo
  c_info "État des services :"
  local ocr_up=0 web_up=0
  curl -sf "http://localhost:$OCR_PORT/health" >/dev/null 2>&1 && ocr_up=1
  curl -sf "http://localhost:$WEB_PORT/"      >/dev/null 2>&1 && web_up=1
  [ "$ocr_up" = 1 ] && c_ok  "  ✔ OCR  (port $OCR_PORT) répond" || c_err "  ✘ OCR  (port $OCR_PORT) ne répond pas — voir $LOG_DIR/ocr.log"
  [ "$web_up" = 1 ] && c_ok  "  ✔ Web  (port $WEB_PORT) répond" || c_err "  ✘ Web  (port $WEB_PORT) ne répond pas — voir $LOG_DIR/web.log"
}

main() {
  case "${1:-start}" in
    stop) stop_services ;;
    mobile) start_ocr; start_web; print_summary; start_mobile ;;
    start|"") start_ocr; start_web; print_summary ;;
    *) c_err "Usage: $0 [start|mobile|stop]"; exit 1 ;;
  esac
}

trap 'echo; c_info "Interruption — arrêt des services…"; stop_services; exit 0' INT TERM
main "$@"
