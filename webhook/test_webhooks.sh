#!/bin/bash

# Тестирование вебхуков LidTracker
# Использование: bash test_webhooks.sh

BASE_URL="http://localhost/admin_panel/webhook"

echo "🧪 Тестирование вебхуков LidTracker"
echo "===================================="
echo ""

# ========================================
# 1. Тест Creatium
# ========================================
echo "📋 Тест 1: Creatium вебхук"
echo "----------------------------"

CREATIUM_DATA=$(cat ../../../LidTracker/Creatium/example_payload.json)

curl -X POST "$BASE_URL/creatium.php" \
  -H "Content-Type: application/json" \
  -d "$CREATIUM_DATA" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.'

echo ""
echo ""

# ========================================
# 2. Тест GCK
# ========================================
echo "📋 Тест 2: GCK вебхук"
echo "----------------------------"

GCK_DATA=$(cat ../../../LidTracker/GCK/example_payload.json)

curl -X POST "$BASE_URL/gck.php" \
  -H "Content-Type: application/json" \
  -d "$GCK_DATA" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.'

echo ""
echo ""

# ========================================
# 3. Тест Marquiz
# ========================================
echo "📋 Тест 3: Marquiz вебхук"
echo "----------------------------"

# Проверяем, существует ли файл с примером
if [ -f "../../../LidTracker/Marquiz/example_payload.json" ]; then
    MARQUIZ_DATA=$(cat ../../../LidTracker/Marquiz/example_payload.json)
else
    # Если файла нет, используем встроенный пример (официальный формат Marquiz)
    MARQUIZ_DATA='{
      "contacts": {
        "name": "Тестовый Клиент",
        "email": "test@marquiz.example",
        "phone": "79995556677"
      },
      "quiz": {
        "id": "marquiz-test-quiz",
        "name": "Тестовый квиз"
      },
      "extra": {
        "utm": {
          "source": "test",
          "medium": "webhook",
          "name": "test_campaign",
          "content": "test_content",
          "term": "test_term"
        },
        "ip": "127.0.0.1",
        "referrer": "http://test.com",
        "href": "http://test.com/quiz"
      }
    }'
fi

curl -X POST "$BASE_URL/marquiz.php" \
  -H "Content-Type: application/json" \
  -d "$MARQUIZ_DATA" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.'

echo ""
echo ""

# ========================================
# 4. Проверка созданных лидов в БД
# ========================================
echo "📊 Проверка лидов в базе данных"
echo "----------------------------"

mysql -h localhost -u datalens_user -pdatalens_readonly_2024 calls_db -e "
    SELECT
        id,
        source,
        phone,
        name,
        status,
        validation_status,
        created_at
    FROM leads
    ORDER BY created_at DESC
    LIMIT 5;
" 2>/dev/null

echo ""
echo ""

# ========================================
# 5. Проверка логов обработки
# ========================================
echo "📝 Логи обработки (последние 10 записей)"
echo "----------------------------"

mysql -h localhost -u datalens_user -pdatalens_readonly_2024 calls_db -e "
    SELECT
        l.lead_id,
        l.step,
        l.status,
        l.message,
        l.created_at
    FROM lead_processing_log l
    ORDER BY l.created_at DESC
    LIMIT 10;
" 2>/dev/null

echo ""
echo "✅ Тестирование завершено!"
