# Troubleshooting - LidTracker Вебхуки

## Проблема: Лид не появляется в базе данных

### 1️⃣ Проверьте логи PHP

```bash
# Смотрим последние ошибки PHP
sudo tail -f /var/log/php_errors.log

# Или логи веб-сервера (Apache)
sudo tail -f /var/log/apache2/error.log

# Или логи веб-сервера (Nginx)
sudo tail -f /var/log/nginx/error.log
```

### 2️⃣ Проверьте, что вебхук доступен извне

```bash
# Тест с внешнего IP (должен вернуть 400 - это нормально)
curl -X POST http://195.239.161.77/admin_panel/webhook/creatium.php \
  -H "Content-Type: application/json" \
  -d '{}'

# Если вернул 200/400/500 - эндпоинт доступен ✅
# Если вернул 404 - файл не найден ❌
# Если вернул 000 - сервер недоступен ❌
```

### 3️⃣ Проверьте права доступа к файлам

```bash
# Проверяем владельца и права
ls -la /home/artem/Domreal_Whisper/admin_panel/webhook/

# Должно быть примерно так:
# -rw-r--r-- www-data www-data creatium.php
# -rw-r--r-- www-data www-data gck.php
# -rw-r--r-- www-data www-data marquiz.php

# Если права неправильные, исправляем:
sudo chown -R www-data:www-data /home/artem/Domreal_Whisper/admin_panel/webhook/
sudo chmod 644 /home/artem/Domreal_Whisper/admin_panel/webhook/*.php
```

### 4️⃣ Проверьте конфигурацию Creatium

В админ-панели Creatium убедитесь:
- ✅ URL вебхука правильный: `http://195.239.161.77/admin_panel/webhook/creatium.php`
- ✅ Метод: `POST`
- ✅ Формат: `JSON`
- ✅ Вебхук включен (активен)

### 5️⃣ Ручной тест вебхука

Создайте тестовый файл `test_payload.json`:

```json
{
  "quiz_id": "test-12345",
  "quiz_name": "Тест квиза",
  "order": {
    "fields": {
      "Номер телефона": "+79991234567",
      "Имя": "Тест Тестович",
      "Email": "test@example.com"
    },
    "utm_source": "test",
    "utm_medium": "manual"
  },
  "visit": {
    "ip": "127.0.0.1",
    "user_agent": "Mozilla/5.0"
  }
}
```

Отправьте его на вебхук:

```bash
curl -X POST http://195.239.161.77/admin_panel/webhook/creatium.php \
  -H "Content-Type: application/json" \
  -d @test_payload.json \
  -v

# Ожидаемый ответ:
# HTTP/1.1 200 OK
# {
#   "success": true,
#   "lead_id": 1,
#   "message": "Lead received and queued for processing"
# }
```

### 6️⃣ Проверьте подключение к базе данных

```bash
# Проверяем, что база данных доступна
mysql -h localhost -u datalens_user -pdatalens_readonly_2024 calls_db -e "SELECT 1;"

# Проверяем, что таблицы существуют
mysql -h localhost -u datalens_user -pdatalens_readonly_2024 calls_db -e "SHOW TABLES;"

# Должны быть таблицы:
# - leads
# - lead_processing_log
# - joywork_sync_queue
# - duplicate_checks
# - routing_rules
# - field_mappings
# - managers
```

### 7️⃣ Проверьте формат данных от Creatium

Включите логирование всех входящих запросов:

Добавьте в начало `/home/artem/Domreal_Whisper/admin_panel/webhook/creatium.php`:

```php
// Временное логирование
$rawPayload = file_get_contents('php://input');
file_put_contents('/tmp/creatium_webhook.log', date('Y-m-d H:i:s') . "\n" . $rawPayload . "\n\n", FILE_APPEND);
```

Затем отправьте тестовый лид и проверьте лог:

```bash
cat /tmp/creatium_webhook.log
```

### 8️⃣ Проверьте firewall и доступность порта

```bash
# Проверяем, открыт ли порт 80
sudo netstat -tlnp | grep :80

# Проверяем правила firewall
sudo iptables -L -n | grep 80

# Проверяем UFW (если используется)
sudo ufw status
```

---

## Проблема: Лид создаётся, но телефон невалидный

### Причина: Неправильный формат телефона

Валидация требует формат: `+79XXXXXXXXX` (11 цифр, начинается с 7)

**Примеры:**
- ✅ Валидные: `+79991234567`, `79991234567`, `89991234567`
- ❌ Невалидные: `9991234567`, `+7 (999) 123-45-67`, `8 999 123 45 67`

**Решение:**

Проверьте, как Creatium отправляет номер телефона:

```bash
cat /tmp/creatium_webhook.log | grep "Номер телефона"
```

Если формат не `+79XXXXXXXXX`, исправьте в настройках Creatium или обновите валидацию в `WebhookReceiver.php`.

---

## Проблема: Дубликаты лидов

### Причина: Дедупликация срабатывает (это нормально!)

Система проверяет дубликаты за последние 24 часа по номеру телефона.

**Проверка:**

```bash
mysql -h localhost -u datalens_user -pdatalens_readonly_2024 calls_db -e "
  SELECT phone, COUNT(*) as count, GROUP_CONCAT(id) as lead_ids
  FROM leads
  WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
  GROUP BY phone
  HAVING count > 1;
"
```

**Решение:**

Если нужно протестировать - используйте разные номера телефонов или очистите таблицу:

```bash
mysql -h localhost -u datalens_user -pdatalens_readonly_2024 calls_db -e "TRUNCATE TABLE leads;"
```

---

## Проблема: Лид не отправляется в JoyWork

### Причина: Воркер синхронизации не настроен

Это нормально! Отправка в JoyWork - это отдельный процесс (worker).

**Проверка очереди:**

```bash
mysql -h localhost -u datalens_user -pdatalens_readonly_2024 calls_db -e "
  SELECT * FROM joywork_sync_queue ORDER BY created_at DESC LIMIT 5;
"
```

Если лиды в очереди - значит вебхук работает ✅

Настройка воркера - это следующий этап интеграции.

---

## Быстрая диагностика

Запустите полную проверку:

```bash
bash /home/artem/Domreal_Whisper/admin_panel/webhook/check_webhook_status.sh
```

Или откройте веб-интерфейс:

```
http://195.239.161.77/admin_panel/webhook/status.php
```

---

## Нужна помощь?

Если проблема не решается:

1. Соберите диагностическую информацию:

```bash
# Создайте файл с диагностикой
cat > /tmp/webhook_diagnostic.txt <<EOF
=== PHP Error Log ===
$(sudo tail -50 /var/log/php_errors.log 2>/dev/null || echo "No PHP errors")

=== Apache/Nginx Error Log ===
$(sudo tail -50 /var/log/apache2/error.log 2>/dev/null || sudo tail -50 /var/log/nginx/error.log 2>/dev/null || echo "No web server errors")

=== Recent Leads ===
$(mysql -h localhost -u datalens_user -pdatalens_readonly_2024 calls_db -e "SELECT * FROM leads ORDER BY created_at DESC LIMIT 5;" 2>/dev/null)

=== Recent Logs ===
$(mysql -h localhost -u datalens_user -pdatalens_readonly_2024 calls_db -e "SELECT * FROM lead_processing_log ORDER BY created_at DESC LIMIT 10;" 2>/dev/null)

=== File Permissions ===
$(ls -la /home/artem/Domreal_Whisper/admin_panel/webhook/)

=== Creatium Webhook Log ===
$(cat /tmp/creatium_webhook.log 2>/dev/null || echo "No webhook log")
EOF

cat /tmp/webhook_diagnostic.txt
```

2. Отправьте этот вывод для анализа
