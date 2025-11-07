# Отладка аналитики

## Как проверить, почему графики не отображаются

### 1. Откройте консоль браузера

**Chrome/Edge:**
- Нажмите F12
- Перейдите на вкладку "Console"

**Firefox:**
- Нажмите F12
- Перейдите на вкладку "Консоль"

### 2. Обновите страницу

Нажмите Ctrl+F5 для полного обновления страницы.

### 3. Проверьте логи в консоли

Вы должны увидеть:

```
Loading dashboard data with queryString: date_from=...
All API responses: {kpiData: {...}, funnelData: {...}, ...}
updateDepartmentsChart data: {...}
updateManagersChart data: {...}
```

### 4. Если видите ошибки

#### Ошибка "Failed to fetch"
- API endpoints не доступны
- Проверьте, что Apache запущен: `sudo systemctl status apache2`

#### Ошибка "Departments API error" или "Managers API error"
- API возвращает ошибку
- Проверьте логи Apache: `tail -f /var/log/apache2/domreal-admin-error.log`

#### "No departments data" или "No managers data"
- API возвращает пустые данные
- Проверьте, есть ли данные в БД:
```sql
SELECT COUNT(*) FROM calls_raw;
SELECT COUNT(*) FROM analysis_results;
```

### 5. Проверьте вкладку Network

1. Откройте вкладку "Network" (Сеть)
2. Обновите страницу
3. Найдите запросы к:
   - `/api/analytics/departments.php`
   - `/api/analytics/managers.php`
4. Кликните на запрос
5. Посмотрите вкладку "Response" - что возвращает API

### 6. Если графики все еще не работают

Проверьте, что ECharts загружен:

В консоли введите:
```javascript
typeof echarts
```

Должно вывести: `"object"`

Если выводит `"undefined"` - CDN ECharts не загрузился.

### 7. Ручная проверка API

Откройте в браузере:
```
https://domrilhost.ru/api/analytics/departments.php?date_from=2025-10-01&date_to=2025-10-08
```

Должен вернуть JSON с данными:
```json
{
  "success": true,
  "data": {
    "departments": ["7 отдел Ягофарова Юлия", ...],
    "total_calls": [457, ...],
    "successful_calls": [120, ...]
  }
}
```

## Частые проблемы

### Проблема: Графики пустые, но API возвращает данные

**Решение:** Проверьте, что в данных есть хотя бы 1 элемент:
```javascript
console.log(departmentsData.data.departments.length);
```

Если 0 - значит нет данных для выбранного периода.

### Проблема: API endpoints таймаутят (timeout)

**Причина:** Медленные SQL запросы или проблема с подключением к MySQL.

**Решение:**
1. Проверьте подключение к MySQL:
```bash
mysql -u datalens_user -p calls_db
```

2. Проверьте индексы:
```sql
SHOW INDEX FROM calls_raw;
SHOW INDEX FROM analysis_results;
```

3. Добавьте индексы, если их нет:
```sql
CREATE INDEX idx_started_at ON calls_raw(started_at_utc);
CREATE INDEX idx_department ON calls_raw(department);
CREATE INDEX idx_callid ON analysis_results(callid);
CREATE INDEX idx_employee ON analysis_results(employee_full_name);
```

### Проблема: "Database connection failed"

**Решение:**
1. Проверьте `admin_panel/config/database.php`
2. Убедитесь, что MySQL запущен: `sudo systemctl status mysql`
3. Проверьте права пользователя:
```sql
SHOW GRANTS FOR 'datalens_user'@'localhost';
```

## Контакты

Если проблема не решается, отправьте в issue:
- Скриншот консоли браузера (F12 → Console)
- Вывод `tail -30 /var/log/apache2/domreal-admin-error.log`
- Вывод `SELECT COUNT(*) FROM calls_raw;`
