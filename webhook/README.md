# LidTracker - Вебхук Эндпоинты

Этот каталог содержит вебхук эндпоинты для приема лидов от сервисов лидогенерации.

## 🌐 URL-адреса вебхуков

### Производственные URL (замените на ваш домен)

```
✅ Creatium:  https://ваш-домен.ru/admin_panel/webhook/creatium.php
✅ GCK:       https://ваш-домен.ru/admin_panel/webhook/gck.php
✅ Marquiz:   https://ваш-домен.ru/admin_panel/webhook/marquiz.php
```

### Локальные URL (для тестирования)

```
http://localhost/admin_panel/webhook/creatium.php
http://localhost/admin_panel/webhook/gck.php
http://localhost/admin_panel/webhook/marquiz.php
```

---

## 📋 Формат запросов

### Метод: `POST`
### Content-Type: `application/json`

### Пример запроса (Creatium)

```bash
curl -X POST https://ваш-домен.ru/admin_panel/webhook/creatium.php \
  -H "Content-Type: application/json" \
  -d '{
    "quiz_id": "12345",
    "quiz_name": "Квиз подбора недвижимости",
    "order": {
      "fields": {
        "Номер телефона": "+79991234567",
        "Имя": "Иван Иванов"
      },
      "utm_source": "yandex",
      "utm_medium": "cpc"
    }
  }'
```

### Успешный ответ (HTTP 200)

```json
{
  "success": true,
  "lead_id": 123,
  "message": "Lead received and queued for processing"
}
```

### Ответ с ошибкой (HTTP 500)

```json
{
  "success": false,
  "error": "Невалидный телефон"
}
```

---

## 🔄 Процесс обработки вебхука

Каждый вебхук проходит следующие этапы:

### 1️⃣ **Получение (received)**
- Принимается POST запрос с JSON
- Сохраняется raw payload в таблицу `leads`
- Создается запись в логах (`lead_processing_log`)

### 2️⃣ **Валидация (validated)**
- Извлекается номер телефона
- Проверяется формат: `+79XXXXXXXXX`
- Нормализуется телефон

### 3️⃣ **Дедупликация (deduplicated)**
- Проверка на дубликаты за последние 24 часа
- Если дубликат найден → статус `duplicate`
- Если нет → продолжаем обработку

### 4️⃣ **Нормализация (normalized)**
- Маппинг полей источника → стандартные поля
- Извлечение UTM меток, IP, User-Agent и т.д.
- Обновление записи в таблице `leads`

### 5️⃣ **Постановка в очередь (enqueued)**
- Добавление в таблицу `joywork_sync_queue`
- Статус: `pending`
- Ожидание обработки воркером

---

## 🗂️ Структура файлов

```
webhook/
├── creatium.php              # Эндпоинт для Creatium
├── gck.php                   # Эндпоинт для GCK
├── marquiz.php               # Эндпоинт для Marquiz
├── test_webhooks.sh          # Скрипт тестирования
└── README.md                 # Эта документация

../lidtracker/classes/
└── WebhookReceiver.php       # Базовый класс обработки
```

---

## 🧪 Тестирование

### Автоматическое тестирование всех вебхуков

```bash
cd /home/artem/Domreal_Whisper/admin_panel/webhook
bash test_webhooks.sh
```

Скрипт протестирует все три вебхука и покажет:
- ✅ Ответы от каждого эндпоинта
- 📊 Созданные лиды в БД
- 📝 Логи обработки

### Ручное тестирование (Creatium)

```bash
curl -X POST http://localhost/admin_panel/webhook/creatium.php \
  -H "Content-Type: application/json" \
  -d @../../../LidTracker/Creatium/example_payload.json
```

### Ручное тестирование (GCK)

```bash
curl -X POST http://localhost/admin_panel/webhook/gck.php \
  -H "Content-Type: application/json" \
  -d @../../../LidTracker/GCK/example_payload.json
```

### Ручное тестирование (Marquiz)

```bash
curl -X POST http://localhost/admin_panel/webhook/marquiz.php \
  -H "Content-Type: application/json" \
  -d @../../../LidTracker/Marquiz/example_payload.json
```

---

## 📊 Проверка результатов

### Просмотр созданных лидов

```bash
mysql -h localhost -u datalens_user -pdatalens_readonly_2024 calls_db -e "
  SELECT id, source, phone, name, status, created_at
  FROM leads
  ORDER BY created_at DESC
  LIMIT 10;
"
```

### Просмотр логов обработки

```bash
mysql -h localhost -u datalens_user -pdatalens_readonly_2024 calls_db -e "
  SELECT lead_id, step, status, message
  FROM lead_processing_log
  WHERE lead_id = 123
  ORDER BY created_at ASC;
"
```

### Просмотр очереди JoyWork

```bash
mysql -h localhost -u datalens_user -pdatalens_readonly_2024 calls_db -e "
  SELECT lead_id, status, attempts, next_attempt_at
  FROM joywork_sync_queue
  ORDER BY created_at DESC
  LIMIT 10;
"
```

---

## ⚙️ Настройка в сервисах

### Creatium

1. Войдите в админ-панель Creatium
2. Откройте раздел **"Интеграции"** → **"Webhooks"**
3. Добавьте URL: `https://ваш-домен.ru/admin_panel/webhook/creatium.php`
4. Метод: `POST`, Формат: `JSON`

### GCK

1. Войдите в админ-панель GCK
2. Настройте вебхук на URL: `https://ваш-домен.ru/admin_panel/webhook/gck.php`
3. Формат: `JSON`

### Marquiz

1. Войдите в админ-панель Marquiz (https://marquiz.ru)
2. Откройте раздел **"Интеграции"** → **"Webhooks"**
3. Добавьте URL: `https://ваш-домен.ru/admin_panel/webhook/marquiz.php`
4. Метод: `POST`, Формат: `JSON`

**Формат данных Marquiz** (согласно официальной документации):
```json
{
  "contacts": {
    "name": "Имя",
    "email": "email@email.ru",
    "phone": "89851234567"
  },
  "quiz": {
    "id": "600920a2de60d9004900edb9",
    "name": "Название квиза"
  },
  "extra": {
    "utm": {
      "source": "test_source",
      "medium": "test_medium",
      "name": "test_campaign",
      "content": "test_content",
      "term": "test_term"
    },
    "ip": "111.11.111.111",
    "referrer": "http://example.com",
    "href": "http://example.com"
  }
}
```

**Извлекаемые поля:**
- Телефон: `contacts.phone` → нормализуется в +79XXXXXXXXX
- Имя: `contacts.name`
- Email: `contacts.email`
- Quiz ID: `quiz.id`
- UTM метки: `extra.utm.*` (campaign извлекается из `utm.name`)
- IP: `extra.ip`
- Referer: `extra.referrer`
- Page URL: `extra.href`

---

## 🔒 Безопасность

### Рекомендации:

1. **HTTPS обязательно** - используйте SSL сертификат
2. **IP Whitelist** - ограничьте доступ по IP адресам источников
3. **Signature проверка** - добавьте валидацию подписи (если поддерживается)
4. **Rate Limiting** - ограничьте количество запросов (100/мин)

### Пример добавления IP whitelist в `.htaccess`

```apache
<Files "creatium.php">
    Order Deny,Allow
    Deny from all
    Allow from 185.x.x.x  # IP Creatium
</Files>
```

---

## 📚 Дополнительные ресурсы

- **Спецификация админ-панели:** `../lidtracker/ADMIN_PANEL_SPEC.md`
- **Архитектура:** `../../LidTracker/ARCHITECTURE.md`
- **Примеры данных:**
  - Creatium: `../../LidTracker/Creatium/example_payload.json`
  - GCK: `../../LidTracker/GCK/example_payload.json`
  - Marquiz: `../../LidTracker/Marquiz/example_payload.json`

---

**Версия:** 1.0
**Дата создания:** 25 октября 2025
