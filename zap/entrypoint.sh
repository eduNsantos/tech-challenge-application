#!/bin/bash
set -e

API_URL="http://app-php:8000/api"
ZAP_PORT=8090
ZAP_API_KEY="zap-local-key"
ZAP_ACTIVE_SCAN="${ZAP_ACTIVE_SCAN:-false}"

# ── 1. Obtém token JWT ────────────────────────────────────────────────────────
echo "[ZAP] Autenticando (${ZAP_EMAIL})..."
RESPONSE=$(curl -sf -X POST "${API_URL}/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"${ZAP_EMAIL}\",\"password\":\"${ZAP_PASSWORD}\"}" || echo "")

TOKEN=$(echo "$RESPONSE" | python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])" 2>/dev/null || echo "")

if [ -z "$TOKEN" ]; then
  echo "[ZAP] ERRO: login falhou. Resposta: ${RESPONSE}"
  exit 1
fi
echo "[ZAP] Token obtido."

# ── 2. Inicia ZAP como daemon (proxy + API) ───────────────────────────────────
JAVA_OPTS="-Xmx1g -Xms256m" zap.sh -daemon \
  -host 127.0.0.1 \
  -port "${ZAP_PORT}" \
  -config api.key="${ZAP_API_KEY}" \
  -config api.addrs.addr.name=".*" \
  -config api.addrs.addr.regex=true \
  > /tmp/zap.log 2>&1 &
ZAP_PID=$!

echo "[ZAP] Aguardando ZAP inicializar (PID=${ZAP_PID})..."
until curl -sf "http://127.0.0.1:${ZAP_PORT}/JSON/core/view/version/?apikey=${ZAP_API_KEY}" > /dev/null 2>&1; do
  if ! kill -0 "$ZAP_PID" 2>/dev/null; then
    echo "[ZAP] ERRO: ZAP daemon morreu durante inicializacao."
    tail -20 /tmp/zap.log
    exit 1
  fi
  sleep 3
done
echo "[ZAP] ZAP pronto."

# ── 3. Sonda todos os endpoints via proxy ZAP com Bearer token ────────────────
# Requests passam pelo proxy ZAP → replacer injeta header → respostas reais capturadas
echo "[ZAP] Sondando endpoints autenticados via proxy..."
export TOKEN API_URL ZAP_PORT

cat > /tmp/probe.py << 'PYEOF'
import yaml, subprocess, os, sys

with open('/zap/wrk/openapi.yaml') as f:
    spec = yaml.safe_load(f)

API_URL = os.environ['API_URL']
TOKEN   = os.environ['TOKEN']
ZAP_PORT = os.environ['ZAP_PORT']
PROXY   = f'http://127.0.0.1:{ZAP_PORT}'
AUTH    = f'Bearer {TOKEN}'

sample = {
    'id':        'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
    'serviceId': 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
    'itemId':    'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
    'token':     'sample-token',
}

paths = spec.get('paths', {})
results = []

for path, methods in paths.items():
    for method, info in methods.items():
        if method.lower() not in ('get', 'post', 'put', 'delete', 'patch'):
            continue
        url_path = path
        for param, val in sample.items():
            url_path = url_path.replace('{' + param + '}', val)
        url = f"{API_URL}{url_path}"
        cmd = ['curl', '-s', '-o', '/dev/null', '-w', '%{http_code}',
               '-x', PROXY,
               '-X', method.upper(),
               '-H', f'Authorization: {AUTH}',
               '-H', 'Content-Type: application/json',
               '--max-time', '10',
               url]
        try:
            r = subprocess.run(cmd, capture_output=True, text=True, timeout=15)
            status = r.stdout.strip()
        except Exception as e:
            status = 'ERR'
        print(f"{method.upper():<8} {status:<6} {url_path}", flush=True)
        results.append((method.upper(), status, url_path))

print(f"\nTotal: {len(results)} endpoints sondados.", flush=True)
PYEOF

python3 /tmp/probe.py

# ── 4. Aguarda passive scan processar o trafego capturado ─────────────────────
echo "[ZAP] Aguardando passive scan concluir..."
sleep 5
while true; do
  if ! kill -0 "$ZAP_PID" 2>/dev/null; then
    echo "[ZAP] ERRO: ZAP daemon morreu durante passive scan."
    tail -20 /tmp/zap.log; exit 1
  fi
  PENDING=$(curl -sf "http://127.0.0.1:${ZAP_PORT}/JSON/pscan/view/recordsToScan/?apikey=${ZAP_API_KEY}" \
    | python3 -c "import sys,json; print(json.load(sys.stdin)['recordsToScan'])" 2>/dev/null || echo "1")
  [ "$PENDING" = "0" ] && break
  echo "[ZAP] Passive scan: ${PENDING} registros pendentes..."
  sleep 3
done
echo "[ZAP] Passive scan concluido."

# ── 5. Active scan (opcional — requer ZAP_ACTIVE_SCAN=true) ──────────────────
if [ "$ZAP_ACTIVE_SCAN" = "true" ]; then
  echo "[ZAP] Iniciando active scan (ZAP_ACTIVE_SCAN=true)..."
  SCAN_ID=$(curl -sf -G "http://127.0.0.1:${ZAP_PORT}/JSON/ascan/action/scan/" \
    --data-urlencode "apikey=${ZAP_API_KEY}" \
    --data-urlencode "url=${API_URL}" \
    --data-urlencode "recurse=true" \
    | python3 -c "import sys,json; print(json.load(sys.stdin)['scan'])" 2>/dev/null)

  while true; do
    if ! kill -0 "$ZAP_PID" 2>/dev/null; then
      echo "[ZAP] AVISO: ZAP daemon morreu durante active scan. Gerando relatorio parcial..."
      break
    fi
    STATUS=$(curl -sf "http://127.0.0.1:${ZAP_PORT}/JSON/ascan/view/status/?apikey=${ZAP_API_KEY}&scanId=${SCAN_ID}" \
      | python3 -c "import sys,json; print(json.load(sys.stdin)['status'])" 2>/dev/null || echo "0")
    echo "[ZAP] Active scan: ${STATUS}%"
    [ "$STATUS" = "100" ] && break
    sleep 10
  done
else
  echo "[ZAP] Active scan desabilitado. Use ZAP_ACTIVE_SCAN=true para habilitar (~3 GB RAM necessarios)."
fi

# ── 6. Gera relatórios ────────────────────────────────────────────────────────
echo "[ZAP] Gerando relatorios..."
mkdir -p /zap/wrk/reports
curl -sf "http://127.0.0.1:${ZAP_PORT}/OTHER/core/other/htmlreport/?apikey=${ZAP_API_KEY}" \
  -o /zap/wrk/reports/report.html
curl -sf "http://127.0.0.1:${ZAP_PORT}/OTHER/core/other/jsonreport/?apikey=${ZAP_API_KEY}" \
  -o /zap/wrk/reports/report.json

echo ""
echo "[ZAP] Scan concluido. Relatorios em /zap/wrk/reports/"
