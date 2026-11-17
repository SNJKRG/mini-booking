#!/usr/bin/env bash
# Проверка реальной конкурентности: два пересекающихся HTTP POST на один свободный будущий слот.
# Нужны: основная БД после migrate и db:seed и сервер с несколькими воркерами:
#   PHP_CLI_SERVER_WORKERS=4 php artisan serve --no-reload   (без --no-reload artisan молча запускает ОДИН воркер)
# Запуск: scripts/concurrency-check.sh [base_url]
set -euo pipefail

BASE_URL="${1:-http://127.0.0.1:8000}"
HOLD_SECONDS=3

set -a; source .env; set +a
export PGPASSWORD="$DB_PASSWORD"
PSQL=(psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" -v ON_ERROR_STOP=1 -Atq)

SLOT_ID=$("${PSQL[@]}" -c "SELECT s.id FROM slots s WHERE s.starts_at > now() + interval '1 hour'
  AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.slot_id = s.id AND b.cancelled_at IS NULL)
  ORDER BY s.starts_at LIMIT 1")
[ -n "$SLOT_ID" ] || { echo "Нет свободного будущего слота, запустите php artisan db:seed"; exit 1; }
echo "Проверяемый слот: $SLOT_ID"

# Отдельная сессия держит блокировку строки слота, чтобы оба запроса гарантированно пересеклись.
"${PSQL[@]}" -c "BEGIN; SELECT id FROM slots WHERE id = $SLOT_ID FOR UPDATE; SELECT pg_sleep($HOLD_SECONDS); COMMIT;" >/dev/null &
LOCK_PID=$!
sleep 0.5

TMP=$(mktemp -d)
for who in one two; do
  curl -s -o "$TMP/$who.body" -w '%{http_code}' -X POST "$BASE_URL/api/bookings" \
    -H 'Accept: application/json' -H 'Content-Type: application/json' \
    -d "{\"slot_id\": $SLOT_ID, \"customer_name\": \"Concurrent $who\", \"customer_email\": \"$who@example.com\"}" \
    > "$TMP/$who.code" &
done

sleep 1
WAITING=$("${PSQL[@]}" -c "SELECT count(*) FROM pg_stat_activity WHERE datname = current_database() AND wait_event_type = 'Lock'")
echo "Запросов, одновременно ждущих блокировку слота: $WAITING (ожидается 2)"

wait
CODES=$(printf "%s\n%s\n" "$(cat "$TMP/one.code")" "$(cat "$TMP/two.code")" | sort | tr "\n" " ")
echo "HTTP-коды: $CODES(ожидается: 201 409)"
echo "Тела ответов: $(cat "$TMP/one.body") | $(cat "$TMP/two.body")"
ACTIVE=$("${PSQL[@]}" -c "SELECT count(*) FROM bookings WHERE slot_id = $SLOT_ID AND cancelled_at IS NULL")
echo "Активных броней у слота $SLOT_ID: $ACTIVE (ожидается 1)"

echo "Частичный индекс без приложения (прямой INSERT второй активной брони):"
"${PSQL[@]}" -c "INSERT INTO bookings (slot_id, customer_name, customer_email, created_at, updated_at)
  VALUES ($SLOT_ID, 'Direct insert', 'direct@example.com', now(), now())" 2>&1 | sed 's/^/  /' || true

rm -rf "$TMP"
[ "$CODES" = "201 409 " ] && [ "$ACTIVE" = "1" ] && [ "$WAITING" = "2" ] && echo "OK" || { echo "ПРОВАЛ"; exit 1; }
