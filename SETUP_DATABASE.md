# 📊 Инструкция по импорту базы данных

## Проблема
База данных `calls_db (1).sql` не импортирована, так как пользователь `datalens_user` имеет только READ-ONLY доступ.

## Решение

### Вариант 1: Импорт с правами администратора (рекомендуется)

```bash
# Импортируем дамп от имени root или другого пользователя с правами на INSERT
mysql -h 127.0.0.1 -P 3306 -u root -p calls_db < "/home/coder/projects/MORDA/calls_db (1).sql"
```

### Вариант 2: Предоставить права пользователю datalens_user

```sql
-- Подключитесь к MySQL как root
mysql -h 127.0.0.1 -P 3306 -u root -p

-- Предоставьте права на запись
GRANT INSERT, UPDATE, DELETE ON calls_db.* TO 'datalens_user'@'%';
FLUSH PRIVILEGES;
EXIT;

-- Теперь импортируйте дамп
mysql -h 127.0.0.1 -P 3306 -u datalens_user -p'datalens_readonly_2024' calls_db < "/home/coder/projects/MORDA/calls_db (1).sql"
```

### Вариант 3: Создать нового пользователя с полными правами

```sql
-- Подключитесь к MySQL как root
mysql -h 127.0.0.1 -P 3306 -u root -p

-- Создайте нового пользователя
CREATE USER 'calls_admin'@'%' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON calls_db.* TO 'calls_admin'@'%';
FLUSH PRIVILEGES;
EXIT;

-- Импортируйте дамп
mysql -h 127.0.0.1 -P 3306 -u calls_admin -p'strong_password_here' calls_db < "/home/coder/projects/MORDA/calls_db (1).sql"
```

## После импорта

### Проверьте количество записей:

```bash
mysql -h 127.0.0.1 -P 3306 -u datalens_user -p'datalens_readonly_2024' calls_db -e "
SELECT
  (SELECT COUNT(*) FROM calls_raw) as calls,
  (SELECT COUNT(*) FROM transcripts) as transcripts,
  (SELECT COUNT(*) FROM analysis_results) as analysis,
  (SELECT COUNT(*) FROM audio_jobs) as audio_jobs;
"
```

Должно быть примерно:
- **calls_raw**: ~4,296 записей
- **transcripts**: ~4,293 записей
- **analysis_results**: ~4,285 записей
- **audio_jobs**: ~4,296 записей

### Перезапустите frontend для проверки:

```bash
cd /home/coder/projects/MORDA
docker-compose restart

# Проверьте API
curl "http://localhost:8080/api/calls.php?page=1&per_page=5"
```

## Структура базы данных

После импорта у вас будут следующие таблицы:

1. **calls_raw** - Исходные данные звонков из Beeline API
2. **audio_jobs** - Задачи на скачивание и обработку аудио
3. **transcripts** - Результаты GPU транскрибации (Whisper + pyannote)
4. **analysis_jobs** - Задачи на LLM анализ
5. **analysis_results** - Результаты анализа звонков (GigaChat)
6. **employees** - Справочник сотрудников (пустая)
7. **joywork_push_log** - Лог отправки в CRM (пустая)
8. **system_events** - Системные события (пустая)

## Важно!

⚠️ **Обновите API endpoints** после импорта базы, так как структура таблицы `analysis_results` отличается от ожидаемой в документации:

### Реальная структура analysis_results:
- ✅ `call_type` - тип звонка
- ✅ `summary_text` - краткое саммари
- ✅ `score_overall` - общая оценка (вместо script_compliance_score)
- ✅ `emotion_tone` - эмоциональный тон
- ✅ `conversion_probability` - вероятность конверсии
- ✅ `metrics_json` - дополнительные метрики (JSON)
- ❌ `call_result` - НЕТ (используйте metrics_json)
- ❌ `is_successful` - НЕТ (используйте metrics_json)
- ❌ `script_compliance_score` - НЕТ (используйте score_overall)
- ❌ `script_check_*` - НЕТ (используйте metrics_json)

**Нужно обновить файлы:**
- `api/calls.php` - изменить SELECT запрос
- `api/call_details.php` - изменить SELECT запрос и формирование чеклиста
- `assets/js/calls_list.js` - адаптировать отображение оценок
- `assets/js/call_evaluation.js` - парсить metrics_json

## Временное решение (до обновления API)

Создайте VIEW для обратной совместимости:

```sql
CREATE OR REPLACE VIEW analysis_results_compat AS
SELECT
  id,
  callid,
  llm_model,
  prompt_version,
  raw_response,
  call_type,
  summary_text,
  score_overall as script_compliance_score,
  JSON_UNQUOTE(JSON_EXTRACT(metrics_json, '$.call_result')) as call_result,
  CASE
    WHEN conversion_probability > 0.5 THEN 1
    ELSE 0
  END as is_successful,
  JSON_UNQUOTE(JSON_EXTRACT(metrics_json, '$.success_reason')) as success_reason,
  metrics_json,
  coaching_text as script_check_details,
  0 as script_check_location,
  0 as script_check_payment,
  0 as script_check_goal,
  0 as script_check_is_local,
  0 as script_check_budget,
  created_at
FROM analysis_results;
```

Затем в API используйте `FROM analysis_results_compat` вместо `FROM analysis_results`.
