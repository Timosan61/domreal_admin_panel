<?php
/**
 * API для получения логов Creatium webhook
 */

header('Content-Type: application/json');

// Читаем все 3 лога
$creatiumLog = __DIR__ . '/creatium_debug.log';
$gckLog = __DIR__ . '/gck_debug.log';
$marquizLog = __DIR__ . '/marquiz_debug.log';

$allEntries = [];

// Функция для чтения лога
function parseLog($logFile, $source) {
    if (!file_exists($logFile) || !filesize($logFile)) {
        return [];
    }

    $content = file_get_contents($logFile);
    $lines = explode("\n", trim($content));
    $entries = [];
    $currentEntry = null;

    foreach ($lines as $line) {
        if (empty(trim($line))) {
            if ($currentEntry) {
                $currentEntry['source'] = $source;
                $entries[] = $currentEntry;
                $currentEntry = null;
            }
            continue;
        }

        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
            if ($currentEntry) {
                $currentEntry['source'] = $source;
                $entries[] = $currentEntry;
            }
            $currentEntry = [
                'timestamp' => $matches[1],
                'content' => $line,
                'type' => 'info'
            ];
        } else if ($currentEntry) {
            $currentEntry['content'] .= "\n" . $line;

            if (strpos($line, '✅ SUCCESS') !== false) {
                $currentEntry['type'] = 'success';
            } else if (strpos($line, '❌ ERROR') !== false) {
                $currentEntry['type'] = 'error';
            }
        }
    }

    if ($currentEntry) {
        $currentEntry['source'] = $source;
        $entries[] = $currentEntry;
    }

    return $entries;
}

// Парсим все 3 лога
$allEntries = array_merge(
    parseLog($creatiumLog, 'Creatium'),
    parseLog($gckLog, 'GCK'),
    parseLog($marquizLog, 'Marquiz')
);

// Сортируем по времени (новые сверху)
usort($allEntries, function($a, $b) {
    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
});

// Последние 50 записей
$allEntries = array_slice($allEntries, 0, 50);

echo json_encode([
    'entries' => $allEntries,
    'count' => count($allEntries)
], JSON_UNESCAPED_UNICODE);
exit;

// Старый код ниже не выполнится
$logFile = __DIR__ . '/creatium_debug.log';

if (!file_exists($logFile)) {
    echo json_encode([
        'entries' => [],
        'count' => 0
    ]);
    exit;
}

$content = file_get_contents($logFile);
$lines = explode("\n", trim($content));

$entries = [];
$currentEntry = null;

foreach ($lines as $line) {
    if (empty(trim($line))) {
        if ($currentEntry) {
            $entries[] = $currentEntry;
            $currentEntry = null;
        }
        continue;
    }

    // Новая запись начинается с [timestamp]
    if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
        if ($currentEntry) {
            $entries[] = $currentEntry;
        }
        $currentEntry = [
            'timestamp' => $matches[1],
            'content' => $line,
            'type' => 'info'
        ];
    } else if ($currentEntry) {
        $currentEntry['content'] .= "\n" . $line;

        // Определяем тип записи
        if (strpos($line, '✅ SUCCESS') !== false) {
            $currentEntry['type'] = 'success';
        } else if (strpos($line, '❌ ERROR') !== false) {
            $currentEntry['type'] = 'error';
        }
    }
}

// Добавляем последнюю запись
if ($currentEntry) {
    $entries[] = $currentEntry;
}

// Последние 50 записей (новые сверху)
$entries = array_reverse(array_slice($entries, -50));

echo json_encode([
    'entries' => $entries,
    'count' => count($entries)
], JSON_UNESCAPED_UNICODE);
