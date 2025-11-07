#!/bin/bash

# Быстрый тест одного вебхука Creatium
# Использование: bash test_single_webhook.sh

echo "🧪 Тест вебхука Creatium"
echo "========================"
echo ""

# Тестовый payload
TEST_PAYLOAD='{
  "quiz_id": "test-12345",
  "quiz_name": "Тестовый квиз",
  "order": {
    "fields": {
      "Номер телефона": "+79991234567",
      "Имя": "Тест Тестович",
      "Email": "test@example.com"
    },
    "utm_source": "test",
    "utm_medium": "manual",
    "utm_campaign": "webhook_test"
  },
  "visit": {
    "ip": "127.0.0.1",
    "user_agent": "Test-Agent/1.0",
    "referer": "http://test.example.com"
  },
  "page": {
    "url": "http://test.example.com/quiz"
  }
}'

echo "📤 Отправка тестового лида на вебхук..."
echo ""

# Отправка запроса
RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
  -X POST "https://domrilhost.ru/admin_panel/webhook/creatium.php" \
  -H "Content-Type: application/json" \
  -d "$TEST_PAYLOAD")

# Разделение ответа и HTTP кода
HTTP_BODY=$(echo "$RESPONSE" | sed -e 's/HTTP_CODE\:.*//g')
HTTP_CODE=$(echo "$RESPONSE" | tr -d '\n' | sed -e 's/.*HTTP_CODE://')

echo "📋 Ответ сервера:"
echo "$HTTP_BODY" | jq '.' 2>/dev/null || echo "$HTTP_BODY"
echo ""
echo "HTTP Status: $HTTP_CODE"
echo ""

# Анализ ответа
if [ "$HTTP_CODE" == "200" ]; then
    echo "✅ Вебхук работает! Лид принят."

    # Извлекаем lead_id из ответа
    LEAD_ID=$(echo "$HTTP_BODY" | jq -r '.lead_id' 2>/dev/null)

    if [ "$LEAD_ID" != "null" ] && [ -n "$LEAD_ID" ]; then
        echo ""
        echo "🔍 Проверяем созданный лид в БД..."
        echo ""

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
            WHERE id = $LEAD_ID;
        " 2>/dev/null

        echo ""
        echo "📝 Лог обработки для лида #$LEAD_ID:"
        echo ""

        mysql -h localhost -u datalens_user -pdatalens_readonly_2024 calls_db -e "
            SELECT
                step,
                status,
                message,
                created_at
            FROM lead_processing_log
            WHERE lead_id = $LEAD_ID
            ORDER BY created_at ASC;
        " 2>/dev/null
    fi

elif [ "$HTTP_CODE" == "400" ]; then
    echo "⚠️  Ошибка валидации (HTTP 400)"
    echo "Проверьте формат отправляемых данных"

elif [ "$HTTP_CODE" == "500" ]; then
    echo "❌ Ошибка сервера (HTTP 500)"
    echo "Проверьте логи PHP для деталей:"
    echo "  sudo tail -50 /var/log/php_errors.log"

elif [ "$HTTP_CODE" == "404" ]; then
    echo "❌ Эндпоинт не найден (HTTP 404)"
    echo "Проверьте, что файл существует:"
    echo "  ls -la /home/artem/Domreal_Whisper/admin_panel/webhook/creatium.php"

else
    echo "❌ Неожиданный ответ (HTTP $HTTP_CODE)"
    echo "Вебхук может быть недоступен"
fi

echo ""
echo ""
echo "💡 Полезные ссылки:"
echo "   Веб-мониторинг: https://domrilhost.ru/admin_panel/webhook/status.php"
echo "   Админ-панель:   https://domrilhost.ru/admin_panel/lidtracker/leads.php"
echo ""
echo "🔧 Troubleshooting: /home/artem/Domreal_Whisper/admin_panel/webhook/TROUBLESHOOTING.md"
echo ""
