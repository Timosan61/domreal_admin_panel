# 🚀 Инструкция по запуску - Система оценки звонков

## ✅ Сервер успешно запущен!

### 📍 URL для доступа:

**На удаленном сервере:**
```
http://104.248.39.106:8080
```

**Локально (с удаленного сервера):**
```
http://localhost:8080
```

### 📋 Страницы:

1. **Список звонков**: http://104.248.39.106:8080/index.php
2. **API - список звонков**: http://104.248.39.106:8080/api/calls.php
3. **API - фильтры**: http://104.248.39.106:8080/api/filters.php

---

## 🔧 Управление Docker контейнером

### Запуск (если остановлен):
```bash
cd /home/coder/projects/MORDA
docker-compose up -d
```

### Остановка:
```bash
cd /home/coder/projects/MORDA
docker-compose down
```

### Перезапуск:
```bash
cd /home/coder/projects/MORDA
docker-compose restart
```

### Просмотр логов:
```bash
cd /home/coder/projects/MORDA
docker-compose logs -f
```

### Пересборка (после изменения кода):
```bash
cd /home/coder/projects/MORDA
docker-compose up -d --build
```

### Проверка статуса:
```bash
docker ps | grep calls_frontend
```

---

## 🌐 Доступ с вашего компьютера

Если вы работаете на удаленном сервере и хотите открыть в браузере на своем компьютере:

1. Откройте браузер
2. Введите: `http://104.248.39.106:8080`

**Важно:** Убедитесь, что порт 8080 открыт в firewall вашего сервера.

### Проверка firewall (если нет доступа):

```bash
# Ubuntu/Debian
sudo ufw allow 8080/tcp
sudo ufw reload

# CentOS/RHEL
sudo firewall-cmd --permanent --add-port=8080/tcp
sudo firewall-cmd --reload
```

---

## 🐛 Отладка

### Проверить, работает ли контейнер:
```bash
docker ps
```

### Проверить логи ошибок:
```bash
docker-compose logs web
```

### Проверить подключение к базе данных:
```bash
curl http://localhost:8080/api/filters.php
```

Должен вернуть JSON с отделами и менеджерами.

### Войти в контейнер (для отладки):
```bash
docker exec -it calls_frontend bash
```

---

## 📂 Структура проекта

```
MORDA/
├── api/                    # API endpoints
│   ├── calls.php           # Список звонков
│   ├── call_details.php    # Детали звонка
│   ├── filters.php         # Фильтры
│   └── audio_stream.php    # Стриминг аудио
├── assets/
│   ├── css/style.css       # Стили
│   └── js/
│       ├── calls_list.js   # JS для списка
│       └── call_evaluation.js  # JS для оценки
├── config/database.php     # Конфигурация БД
├── index.php               # Главная страница
├── call_evaluation.php     # Страница оценки
├── Dockerfile              # Docker конфигурация
├── docker-compose.yml      # Docker Compose
└── start.sh                # Скрипт запуска
```

---

## 🎯 Функционал

### Страница 1: Список звонков (`/index.php`)
- ✅ Таблица со всеми звонками
- ✅ Фильтры: отдел, менеджер, дата, длительность, номер, оценка
- ✅ Сортировка по колонкам
- ✅ Пагинация (20 записей/страница)

### Страница 2: Оценка звонка (`/call_evaluation.php?callid=xxx`)
- ✅ Аудиоплеер с управлением
- ✅ Транскрипция с диаризацией
- ✅ Чеклист для оценки
- ✅ AI-анализ результатов

---

## 📊 База данных

**Подключение:** Удаленная БД (195.239.161.77:13306)
- База: `calls_db`
- Пользователь: `datalens_user` (read-only)
- Таблицы: `calls_raw`, `transcripts`, `analysis_results`, `audio_jobs`

---

## 💡 Полезные команды

### Быстрый рестарт всего:
```bash
cd /home/coder/projects/MORDA
./start.sh
```

### Остановить и удалить контейнер:
```bash
docker-compose down -v
```

### Просмотреть логи Apache:
```bash
docker exec calls_frontend tail -f /var/log/apache2/error.log
```

### Проверить PHP версию:
```bash
docker exec calls_frontend php -v
```

---

## 🔒 Безопасность

**Важно для production:**

1. Настройте HTTPS (SSL сертификат)
2. Добавьте авторизацию (логин/пароль)
3. Ограничьте доступ к API по IP
4. Включите rate limiting
5. Отключите отображение ошибок PHP

---

## 📞 Тестирование API

### Получить список звонков:
```bash
curl "http://localhost:8080/api/calls.php?page=1&per_page=5"
```

### Получить фильтры:
```bash
curl "http://localhost:8080/api/filters.php"
```

### Получить детали звонка:
```bash
curl "http://localhost:8080/api/call_details.php?callid=YOUR_CALLID"
```

---

## ✅ Готово к использованию!

Откройте в браузере: **http://104.248.39.106:8080**

Если возникнут проблемы, проверьте:
1. Запущен ли Docker контейнер: `docker ps`
2. Доступен ли порт 8080: `sudo ufw status`
3. Логи контейнера: `docker-compose logs`
