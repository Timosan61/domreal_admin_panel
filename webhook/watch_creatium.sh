#!/bin/bash
# Мониторинг Creatium webhook в реальном времени

clear
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║         🔴 МОНИТОРИНГ CREATIUM WEBHOOK (РЕАЛЬНОЕ ВРЕМЯ)      ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "📍 URL: http://195.239.161.77:18080/webhook/creatium.php"
echo "⏰ Запущено: $(date '+%H:%M:%S')"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

LOG_FILE="/home/artem/Domreal_Whisper/admin_panel/webhook/creatium_debug.log"

# Показать последние 5 записей
if [ -f "$LOG_FILE" ] && [ -s "$LOG_FILE" ]; then
    echo "📋 Последние записи:"
    tail -20 "$LOG_FILE"
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
fi

echo "🔴 ОЖИДАНИЕ НОВЫХ ЗАПРОСОВ..."
echo "   (Нажмите Ctrl+C для остановки)"
echo ""

# Следим за логом
tail -f "$LOG_FILE" 2>/dev/null
