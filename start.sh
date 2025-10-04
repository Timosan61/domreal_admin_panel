#!/bin/bash

echo "🚀 Запуск системы оценки звонков..."
echo ""

# Проверяем, запущен ли контейнер
if docker ps | grep -q calls_frontend; then
    echo "⚠️  Контейнер уже запущен. Останавливаем..."
    docker-compose down
fi

# Запускаем контейнер
echo "📦 Сборка и запуск Docker контейнера..."
docker-compose up -d --build

# Ждем запуска
sleep 3

# Проверяем статус
if docker ps | grep -q calls_frontend; then
    echo ""
    echo "✅ Сервер успешно запущен!"
    echo ""
    echo "📋 Информация о доступе:"
    echo "   - Локальный URL: http://localhost:8080"
    echo "   - Список звонков: http://localhost:8080/index.php"
    echo "   - API: http://localhost:8080/api/"
    echo ""
    echo "🔧 Управление:"
    echo "   - Остановить: docker-compose down"
    echo "   - Логи: docker-compose logs -f"
    echo "   - Перезапустить: docker-compose restart"
    echo ""

    # Показываем IP адрес удаленного сервера
    IP=$(hostname -I | awk '{print $1}')
    if [ ! -z "$IP" ]; then
        echo "🌐 Удаленный доступ: http://$IP:8080"
        echo ""
    fi
else
    echo ""
    echo "❌ Ошибка запуска. Проверьте логи:"
    echo "   docker-compose logs"
fi
