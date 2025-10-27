#!/bin/bash

# Скрипт проверки статуса вебхуков LidTracker
# Использование: bash check_webhook_status.sh

echo "🔍 Проверка вебхуков LidTracker"
echo "================================"
echo ""

# ========================================
# 1. Проверка последних 5 лидов в БД
# ========================================
echo "📋 Последние 5 лидов в базе данных:"
echo "-----------------------------------"

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
# 2. Статистика по источникам
# ========================================
echo "📊 Статистика лидов по источникам:"
echo "-----------------------------------"

mysql -h localhost -u datalens_user -pdatalens_readonly_2024 calls_db -e "
    SELECT
        source,
        COUNT(*) as total_leads,
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_leads,
        SUM(CASE WHEN status = 'duplicate' THEN 1 ELSE 0 END) as duplicates,
        SUM(CASE WHEN validation_status = 'valid' THEN 1 ELSE 0 END) as valid_phones,
        SUM(CASE WHEN validation_status = 'invalid' THEN 1 ELSE 0 END) as invalid_phones,
        MAX(created_at) as last_lead_at
    FROM leads
    GROUP BY source;
" 2>/dev/null

echo ""
echo ""

# ========================================
# 3. Последние записи в логе обработки
# ========================================
echo "📝 Последние 10 записей лога обработки:"
echo "---------------------------------------"

mysql -h localhost -u datalens_user -pdatalens_readonly_2024 calls_db -e "
    SELECT
        l.lead_id,
        ld.source,
        ld.phone,
        l.step,
        l.status,
        l.message,
        l.created_at
    FROM lead_processing_log l
    LEFT JOIN leads ld ON l.lead_id = ld.id
    ORDER BY l.created_at DESC
    LIMIT 10;
" 2>/dev/null

echo ""
echo ""

# ========================================
# 4. Проверка очереди JoyWork
# ========================================
echo "📤 Очередь отправки в JoyWork:"
echo "------------------------------"

mysql -h localhost -u datalens_user -pdatalens_readonly_2024 calls_db -e "
    SELECT
        lead_id,
        status,
        attempts,
        next_attempt_at,
        last_error,
        created_at
    FROM joywork_sync_queue
    ORDER BY created_at DESC
    LIMIT 5;
" 2>/dev/null

echo ""
echo ""

# ========================================
# 5. Лиды за последний час
# ========================================
echo "🕐 Лиды за последний час:"
echo "------------------------"

mysql -h localhost -u datalens_user -pdatalens_readonly_2024 calls_db -e "
    SELECT COUNT(*) as leads_last_hour
    FROM leads
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR);
" 2>/dev/null

echo ""
echo ""

# ========================================
# 6. Проверка доступности эндпоинтов
# ========================================
echo "🌐 Проверка доступности вебхуков:"
echo "----------------------------------"

BASE_URL="http://195.239.161.77/admin_panel/webhook"

for endpoint in creatium gck marquiz; do
    echo -n "Проверка $endpoint.php... "

    response=$(curl -s -o /dev/null -w "%{http_code}" \
        -X POST "$BASE_URL/$endpoint.php" \
        -H "Content-Type: application/json" \
        -d '{}')

    if [ "$response" -eq 400 ] || [ "$response" -eq 500 ]; then
        echo "✅ Доступен (HTTP $response - ожидается ошибка валидации)"
    elif [ "$response" -eq 200 ]; then
        echo "✅ Доступен (HTTP $response)"
    else
        echo "❌ Недоступен (HTTP $response)"
    fi
done

echo ""
echo ""

# ========================================
# 7. Рекомендации
# ========================================
echo "💡 Рекомендации по тестированию:"
echo "--------------------------------"
echo "1️⃣  Отправьте тестовый лид из Creatium"
echo "2️⃣  Запустите этот скрипт снова через 10 секунд"
echo "3️⃣  Проверьте таблицу 'Последние 5 лидов'"
echo "4️⃣  Если лид появился - вебхук работает!"
echo ""
echo "🔧 Для ручного тестирования используйте:"
echo "   bash test_webhooks.sh"
echo ""
echo "📊 Для просмотра в админ-панели:"
echo "   http://195.239.161.77/admin_panel/lidtracker/leads.php"
echo ""

echo "✅ Проверка завершена!"
