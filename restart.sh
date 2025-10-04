#!/bin/bash

echo "🔄 Перезапуск контейнера..."

# Останавливаем контейнер
echo "Останавливаем контейнер..."
docker-compose down

# Запускаем заново
echo "Запускаем контейнер..."
docker-compose up -d

# Ждем 3 секунды
sleep 3

# Проверяем статус
if docker ps | grep -q calls_frontend; then
    echo "✅ Контейнер успешно перезапущен!"
    echo ""
    echo "Тестируем API..."
    curl -s "http://localhost:8080/api/filters.php" | head -10
else
    echo "❌ Ошибка перезапуска"
    docker-compose logs
fi
