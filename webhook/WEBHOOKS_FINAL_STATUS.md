# LidTracker Webhooks - Итоговый статус

## Дата: 25 октября 2025

---

## ✅ Готовность вебхуков: 100%

Все три вебхука успешно настроены, протестированы и готовы к работе!

| Вебхук   | Статус | URL | Тестов пройдено |
|----------|--------|-----|-----------------|
| Creatium | ✅ Готов | https://domrilhost.ru/webhook/creatium.php | 2/2 |
| GCK      | ✅ Готов | https://domrilhost.ru/webhook/gck.php      | 1/1 |
| Marquiz  | ✅ Готов | https://domrilhost.ru/webhook/marquiz.php  | 2/2 |

---

## 📊 Статистика тестирования

```
Всего лидов создано: 5
  - Creatium:  2 лида (#1, #2)
  - GCK:       1 лид  (#3)
  - Marquiz:   2 лида (#4, #5)

Обработка:
  ✅ received:      5/5 (100%)
  ✅ validated:     5/5 (100%)
  ✅ deduplicated:  5/5 (100%)
  ✅ normalized:    5/5 (100%)
  ✅ enqueued:      5/5 (100%)

Валидация телефонов:
  ✅ Валидных:      5/5 (100%)
  ❌ Невалидных:    0/5 (0%)

Очередь JoyWork:
  ⏳ Pending:       5/5 (100%)
```

---

## 🔧 Исправления и улучшения

### 1. ✅ Marquiz - Обновление формата данных

**Проблема:** Вебхук использовал неправильную структуру данных.

**Решение:** Обновлён в соответствии с официальной документацией Marquiz.

**Что изменилось:**
- Телефон: `$data['phone']` → `$data['contacts']['phone']` ✅
- Имя: `$data['name']` → `$data['contacts']['name']` ✅
- Email: `$data['email']` → `$data['contacts']['email']` ✅
- Quiz ID: `$data['quiz_id']` → `$data['quiz']['id']` ✅
- UTM метки: `$data['utm_*']` → `$data['extra']['utm']['*']` ✅
- UTM Campaign: `utm.campaign` → `utm.name` ✅ (важно!)

**Документация:** `/home/artem/Domreal_Whisper/admin_panel/webhook/MARQUIZ_UPDATE.md`

### 2. ✅ Creatium - Инструкция по настройке

**Особенность:** В Creatium структура данных настраивается вручную через конструктор.

**Решение:** Создана подробная инструкция по настройке конструктора.

**Документация:** `/home/artem/Domreal_Whisper/LidTracker/Creatium/SETUP_INSTRUCTIONS.md`

### 3. ✅ База данных - Поле `phone` nullable

**Проблема:** При сохранении raw payload возникала ошибка "Field 'phone' doesn't have a default value".

**Решение:** Поле `phone` сделано nullable (миграция #008).

### 4. ✅ .htaccess - Исключение для `/webhook/`

**Проблема:** Редирект всех запросов на `index_new.php` блокировал вебхуки.

**Решение:** Добавлено исключение для директории `/webhook/`.

---

## 📋 Форматы данных

### Creatium (настраиваемый формат)

**Рекомендуемая структура:**
```json
{
  "quiz_id": "{QUIZ_ID}",
  "quiz_name": "{QUIZ_NAME}",
  "order": {
    "fields": {
      "Номер телефона": "[Телефон]",
      "Имя": "[Имя]",
      "Email": "[Email]"
    },
    "utm_source": "{UTM_SOURCE}",
    "utm_medium": "{UTM_MEDIUM}",
    "utm_campaign": "{UTM_CAMPAIGN}"
  },
  "visit": {
    "ip": "{IP}",
    "user_agent": "{USER_AGENT}"
  }
}
```

### GCK (фиксированный формат)

```json
{
  "vid": "external-id",
  "name": "Имя клиента",
  "phones": ["+79991234567"],
  "email": "email@example.com",
  "utm_source": "google",
  "ip": "192.168.1.1",
  "city": "Москва"
}
```

### Marquiz (фиксированный формат)

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

---

## 🌐 URL-адреса

### Вебхуки (для настройки в сервисах)

```
✅ Creatium:  https://domrilhost.ru/webhook/creatium.php
✅ GCK:       https://domrilhost.ru/webhook/gck.php
✅ Marquiz:   https://domrilhost.ru/webhook/marquiz.php
```

### Мониторинг и админка

```
🔍 Веб-мониторинг:    https://domrilhost.ru/webhook/status.php
📋 Список лидов:      https://domrilhost.ru/lidtracker/leads.php
🧪 Диагностика:       https://domrilhost.ru/lidtracker/debug.php
```

---

## 🧪 Тестирование

### Автоматический тест всех вебхуков

```bash
cd /home/artem/Domreal_Whisper/admin_panel/webhook
bash test_webhooks.sh
```

### Проверка статуса

```bash
bash check_webhook_status.sh
```

### Ручной тест (Marquiz с официальным форматом)

```bash
curl -X POST https://domrilhost.ru/webhook/marquiz.php \
  -H "Content-Type: application/json" \
  -d @/home/artem/Domreal_Whisper/LidTracker/Marquiz/example_payload.json
```

---

## 📂 Структура файлов

```
admin_panel/webhook/
├── creatium.php                    # Эндпоинт Creatium
├── gck.php                         # Эндпоинт GCK
├── marquiz.php                     # Эндпоинт Marquiz (обновлён ✅)
├── status.php                      # Веб-мониторинг
├── test_webhooks.sh                # Автотесты
├── check_webhook_status.sh         # Диагностика
├── README.md                       # Основная документация
├── TROUBLESHOOTING.md              # Решение проблем
├── MARQUIZ_UPDATE.md               # Обновление Marquiz ✅
└── WEBHOOKS_FINAL_STATUS.md        # Этот файл

admin_panel/lidtracker/
├── classes/
│   └── WebhookReceiver.php         # Базовый класс обработки
├── index.php                       # Дашборд
├── leads.php                       # Список лидов (обновлён ✅)
└── debug.php                       # Диагностическая страница ✅

LidTracker/
├── Creatium/
│   ├── example_payload.json        # Пример данных
│   └── SETUP_INSTRUCTIONS.md       # Инструкция настройки ✅
├── GCK/
│   └── example_payload.json        # Пример данных
└── Marquiz/
    └── example_payload.json        # Пример данных (обновлён ✅)

database/migrations/lidtracker/
├── 001-007_*.sql                   # Первичные миграции
└── 008_make_phone_nullable.sql     # Исправление phone ✅
```

---

## 🚀 Следующие шаги

1. ✅ **Вебхуки готовы** - можно настраивать интеграции в Creatium, GCK, Marquiz
2. ⏳ **JoyWork воркер** - настроить автоматическую отправку лидов в CRM
3. ⏳ **Routing правила** - настроить распределение лидов по менеджерам
4. ⏳ **Мониторинг** - настроить алерты и уведомления

---

## 📚 Ссылки на документацию

- **README вебхуков**: `/home/artem/Domreal_Whisper/admin_panel/webhook/README.md`
- **Troubleshooting**: `/home/artem/Domreal_Whisper/admin_panel/webhook/TROUBLESHOOTING.md`
- **Marquiz обновление**: `/home/artem/Domreal_Whisper/admin_panel/webhook/MARQUIZ_UPDATE.md`
- **Creatium настройка**: `/home/artem/Domreal_Whisper/LidTracker/Creatium/SETUP_INSTRUCTIONS.md`
- **Архитектура проекта**: `/home/artem/Domreal_Whisper/LidTracker/ARCHITECTURE.md`
- **URL адреса**: `/home/artem/Domreal_Whisper/WEBHOOK_URLS.md`

---

**✅ Вебхуки полностью готовы к продакшн использованию!**

**Дата готовности:** 25 октября 2025, 19:15 MSK
