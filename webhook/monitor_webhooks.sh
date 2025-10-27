#!/bin/bash
# Мониторинг входящих webhook запросов в реальном времени

echo "=== Мониторинг Webhook запросов ==="
echo "Время запуска: $(date)"
echo "Отслеживаются: Creatium, GCK, Marquiz"
echo "Нажмите Ctrl+C для остановки"
echo ""
echo "----------------------------------------"

# Показать последние 5 webhook запросов
echo "📋 Последние 5 webhook запросов:"
tail -50 /var/log/apache2/domreal-admin-access.log | grep -E "webhook/(creatium|gck|marquiz)" | tail -5
echo ""
echo "----------------------------------------"
echo "🔴 ОЖИДАНИЕ НОВЫХ ЗАПРОСОВ..."
echo ""

# Мониторинг в реальном времени
tail -f /var/log/apache2/domreal-admin-access.log | grep --line-buffered -E "webhook/(creatium|gck|marquiz)" | while read line; do
    timestamp=$(echo "$line" | grep -oP '\[\K[^\]]+')
    source=$(echo "$line" | grep -oP 'webhook/\K[^.]+')
    status=$(echo "$line" | grep -oP 'HTTP/1\.[01]" \K[0-9]+')
    ip=$(echo "$line" | awk '{print $1}')

    # Цветовое кодирование по статусу
    if [ "$status" = "200" ]; then
        status_icon="✅"
    elif [ "$status" = "400" ]; then
        status_icon="❌"
    elif [ "$status" = "500" ]; then
        status_icon="🔥"
    else
        status_icon="⚠️"
    fi

    # Определение источника
    if [ "$source" = "creatium" ]; then
        source_icon="🌐"
    elif [ "$source" = "gck" ]; then
        source_icon="💻"
    elif [ "$source" = "marquiz" ]; then
        source_icon="🎯"
    fi

    echo "$status_icon [$timestamp] $source_icon $source | HTTP $status | IP: $ip"
done
