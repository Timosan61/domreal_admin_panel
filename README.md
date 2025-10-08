# 📞 Система оценки звонков - Frontend

Frontend для системы оценки звонков с использованием базы данных `calls_db`.

## 🚀 Технологии

- **Backend**: PHP 7.4+ (PDO для работы с MySQL)
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla JS)
- **База данных**: MySQL 5.7+ / MariaDB 10.3+

## 📁 Структура проекта

```
MORDA/
├── api/                          # API endpoints
│   ├── calls.php                 # Список звонков с фильтрацией
│   ├── call_details.php          # Детали звонка
│   ├── filters.php               # Доступные значения фильтров
│   └── audio_stream.php          # Стриминг аудиофайлов
├── assets/                       # Статические ресурсы
│   ├── css/
│   │   └── style.css             # Стили
│   └── js/
│       ├── calls_list.js         # JS для списка звонков
│       └── call_evaluation.js    # JS для страницы оценки
├── config/
│   └── database.php              # Конфигурация БД
├── index.php                     # Главная страница (список звонков)
├── call_evaluation.php           # Страница оценки звонка
└── README.md                     # Документация
```

## 🔧 Установка и настройка

### 1. Требования

- PHP 7.4 или выше
- MySQL 5.7 или выше (или MariaDB 10.3+)
- Web-сервер (Apache / Nginx)
- PDO расширение для PHP

### 2. Настройка базы данных

База данных уже настроена в `config/database.php`:

```php
// Удаленное подключение (основное)
Host: 195.239.161.77
Port: 13306
Database: calls_db
Username: datalens_user
Password: datalens_readonly_2024

// Локальное подключение (fallback)
Host: localhost
Port: 3306
Database: calls_db
Username: datalens_user
Password: datalens_readonly_2024
```

### 3. Настройка web-сервера

#### Apache

Создайте виртуальный хост:

```apache
<VirtualHost *:80>
    ServerName calls.local
    DocumentRoot /path/to/MORDA

    <Directory /path/to/MORDA>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/calls_error.log
    CustomLog ${APACHE_LOG_DIR}/calls_access.log combined
</VirtualHost>
```

#### Nginx

```nginx
server {
    listen 80;
    server_name calls.local;
    root /path/to/MORDA;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 4. Запуск (локальный сервер PHP)

Для быстрого тестирования можно использовать встроенный сервер PHP:

```bash
cd /path/to/MORDA
php -S localhost:8000
```

Откройте в браузере: `http://localhost:8000`

## 📋 Функционал

### 1. Страница списка звонков (`index.php`)

**Возможности:**
- ✅ Таблица со всеми звонками
- ✅ Фильтрация по:
  - Отделу
  - Менеджеру
  - Дате (от/до)
  - Длительности (мин/макс)
  - Номеру клиента
  - Оценке (script_compliance_score)
  - Типу звонка (first_call / other)
- ✅ Сортировка по колонкам (дата, менеджер, отдел, длительность, оценка)
- ✅ Пагинация (20 записей на страницу)
- ✅ Статистика (всего звонков, на текущей странице)

**API endpoint:** `api/calls.php`

**Параметры запроса:**
```
GET /api/calls.php?department=Отдел&manager=Иванов&date_from=2025-10-01&page=1&sort_by=started_at_utc&sort_order=DESC
```

### 2. Страница оценки звонка (`call_evaluation.php`)

**Возможности:**
- ✅ Основная информация о звонке (дата, менеджер, отдел, клиент, длительность)
- ✅ Аудиоплеер с:
  - Воспроизведение/пауза
  - Перемотка (seek bar)
  - Регулировка громкости
  - Отображение текущего времени / общего времени
  - Поддержка Range запросов (для перемотки)
- ✅ Транскрипция с диаризацией:
  - Разделение по спикерам (Менеджер / Клиент)
  - Временные метки для каждого сегмента
  - Цветовое кодирование (синий - менеджер, зеленый - клиент)
- ✅ Чеклист для оценки (для first_call):
  - Местоположение клиента выяснено
  - Форма оплаты выяснена
  - Цель покупки выяснена
  - Местный ли клиент
  - Бюджет выяснен
  - Общая оценка соблюдения скрипта (%)
- ✅ Результаты AI-анализа:
  - Краткое резюме
  - Результат звонка (показ / перезвон / отказ)
  - Причина успешности/неуспешности
  - Детали проверки скрипта
  - Полный анализ LLM

**API endpoints:**
- `api/call_details.php?callid=xxx` - получение данных звонка
- `api/audio_stream.php?callid=xxx` - стриминг аудио

## 🎨 Дизайн

Дизайн основан на Figma макетах:
- [Список звонков](https://www.figma.com/design/CmdpfFmm4Kv8nWrHp8TA88/Untitled?node-id=1-1492&t=W9RnZl6HM4vuh7qj-4)
- [Страница оценки](https://www.figma.com/design/CmdpfFmm4Kv8nWrHp8TA88/Untitled?node-id=1-2144&t=W9RnZl6HM4vuh7qj-4)

**Цветовая схема:**
- Основной цвет: `#2563eb` (синий)
- Успех: `#10b981` (зеленый)
- Предупреждение: `#f59e0b` (оранжевый)
- Ошибка: `#ef4444` (красный)
- Фон: `#f8fafc` (светло-серый)

## 🔍 Структура базы данных

### Основные таблицы:

1. **`calls_raw`** - Исходные данные звонков
   - `callid` - Уникальный ID звонка
   - `started_at_utc` - Дата/время звонка
   - `employee_name` - Имя менеджера
   - `department` - Отдел
   - `client_phone` - Телефон клиента
   - `duration_sec` - Длительность (секунды)
   - `direction` - Направление (INBOUND/OUTBOUND/MISSED)

2. **`audio_jobs`** - Задачи на обработку аудио
   - `callid` - ID звонка
   - `local_path` - Путь к аудиофайлу
   - `status` - Статус (DONE/ERROR/QUEUED)
   - `file_format` - Формат файла (mp3/wav)

3. **`transcripts`** - Транскрипции звонков
   - `callid` - ID звонка
   - `text` - Полный текст транскрипции
   - `diarization_json` - JSON с разметкой спикеров

4. **`analysis_results`** - Результаты AI-анализа
   - `callid` - ID звонка
   - `call_type` - Тип звонка (first_call/other)
   - `summary_text` - Краткое резюме
   - `call_result` - Результат звонка
   - `is_successful` - Флаг успешности
   - `script_compliance_score` - Оценка соблюдения скрипта (0-1)
   - `script_check_*` - Поля чеклиста

## 📊 API Endpoints

### 1. GET `/api/calls.php`

**Описание:** Получение списка звонков с фильтрацией

**Параметры:**
- `department` - Отдел (необязательно)
- `manager` - Менеджер (необязательно, поиск по LIKE)
- `date_from` - Дата от (необязательно, формат YYYY-MM-DD)
- `date_to` - Дата до (необязательно, формат YYYY-MM-DD)
- `duration_min` - Минимальная длительность (секунды)
- `duration_max` - Максимальная длительность (секунды)
- `client_phone` - Номер клиента (необязательно, поиск по LIKE)
- `rating_min` - Минимальная оценка (0-1)
- `rating_max` - Максимальная оценка (0-1)
- `call_type` - Тип звонка (необязательно)
- `page` - Номер страницы (по умолчанию 1)
- `per_page` - Записей на странице (по умолчанию 20, макс 100)
- `sort_by` - Поле сортировки (started_at_utc, employee_name, department, duration_sec, script_compliance_score)
- `sort_order` - Направление сортировки (ASC/DESC, по умолчанию DESC)

**Ответ:**
```json
{
  "success": true,
  "data": [
    {
      "callid": "xxx",
      "client_phone": "+79001234567",
      "employee_name": "Иванов Иван",
      "department": "Отдел продаж",
      "direction": "INBOUND",
      "duration_sec": 180,
      "started_at_utc": "2025-10-03 10:30:00",
      "call_type": "first_call",
      "summary_text": "Клиент интересуется квартирой...",
      "call_result": "Назначен показ",
      "is_successful": true,
      "script_compliance_score": 0.85
    }
  ],
  "pagination": {
    "total": 4296,
    "page": 1,
    "per_page": 20,
    "total_pages": 215
  }
}
```

### 2. GET `/api/call_details.php?callid=xxx`

**Описание:** Получение детальной информации о звонке

**Параметры:**
- `callid` (обязательно) - ID звонка

**Ответ:**
```json
{
  "success": true,
  "data": {
    "callid": "xxx",
    "employee_name": "Иванов Иван",
    "department": "Отдел продаж",
    "client_phone": "+79001234567",
    "duration_sec": 180,
    "started_at_utc": "2025-10-03 10:30:00",
    "direction": "INBOUND",
    "transcript_text": "Полный текст...",
    "diarization": {
      "segments": [
        {
          "start": 0.0,
          "end": 11.2,
          "speaker": "SPEAKER_00",
          "speaker_role": "Менеджер",
          "text": "Алло, добрый день!"
        }
      ]
    },
    "call_type": "first_call",
    "summary_text": "Краткое резюме...",
    "call_result": "Назначен показ",
    "is_successful": true,
    "script_compliance_score": 0.85,
    "script_check_location": true,
    "script_check_payment": true,
    "checklist": [
      {
        "id": "location",
        "label": "Местоположение клиента выяснено",
        "checked": true,
        "description": "..."
      }
    ]
  }
}
```

### 3. GET `/api/filters.php`

**Описание:** Получение доступных значений для фильтров

**Ответ:**
```json
{
  "success": true,
  "data": {
    "departments": ["Отдел продаж", "Отдел аренды"],
    "managers": ["Иванов Иван", "Петров Петр"],
    "call_types": ["first_call", "other"]
  }
}
```

### 4. GET `/api/audio_stream.php?callid=xxx`

**Описание:** Стриминг аудиофайла звонка

**Параметры:**
- `callid` (обязательно) - ID звонка

**Ответ:** Бинарный аудио поток (Content-Type: audio/mpeg)

**Поддержка:**
- Range запросы для перемотки
- Определение MIME типа по формату файла

## 🐛 Отладка

### Проверка подключения к БД

```php
<?php
include_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

if ($db) {
    echo "✅ Подключение успешно";

    // Тестовый запрос
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM calls_raw");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "<br>📞 Всего звонков: " . $result['total'];
} else {
    echo "❌ Ошибка подключения";
}
?>
```

### Логирование ошибок

Включите отображение ошибок PHP для отладки:

```php
// В начало файла index.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## 🚀 Production рекомендации

1. **Отключите отображение ошибок:**
   ```php
   error_reporting(0);
   ini_set('display_errors', 0);
   ```

2. **Настройте CORS** (если фронтенд на другом домене):
   ```php
   header("Access-Control-Allow-Origin: https://yourdomain.com");
   ```

3. **Добавьте кэширование** для filters.php:
   ```php
   header("Cache-Control: public, max-age=3600");
   ```

4. **Ограничьте доступ к аудио** (добавьте авторизацию):
   ```php
   // В audio_stream.php
   if (!isset($_SESSION['user_id'])) {
       http_response_code(401);
       exit();
   }
   ```

5. **Используйте prepared statements** (уже реализовано)

6. **Добавьте rate limiting** для API endpoints

## 📝 Лицензия

Проприетарное ПО. Все права защищены.

## 👥 Авторы

- Frontend разработка: Claude AI
- База данных: calls_db (существующая структура)
- Дизайн: Рома Чечулин (Figma макеты)
