<?php
// Тестовая страница для проверки API ответа
session_start();

// Эмуляция авторизации
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin1';
$_SESSION['role'] = 'admin';

$callid = $_GET['callid'] ?? '303712538';

// Запрос к API
$url = "http://localhost/api/call_details.php?callid=" . urlencode($callid);
$context = stream_context_create([
    'http' => [
        'header' => 'Cookie: PHPSESSID=' . session_id()
    ]
]);

$response = file_get_contents($url, false, $context);
$data = json_decode($response, true);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test API Response</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; }
        .highlight { background: yellow; }
    </style>
</head>
<body>
    <h1>API Response Test for callid: <?= htmlspecialchars($callid) ?></h1>

    <h2>Raw JSON:</h2>
    <pre><?= htmlspecialchars($response) ?></pre>

    <h2>Parsed Data:</h2>
    <pre><?= print_r($data, true) ?></pre>

    <?php if (isset($data['data'])): ?>
    <h2>Important Fields:</h2>
    <ul>
        <li>audio_status: <strong><?= htmlspecialchars($data['data']['audio_status'] ?? 'NULL') ?></strong></li>
        <li class="<?= !empty($data['data']['audio_error']) ? 'highlight' : '' ?>">
            audio_error: <strong><?= htmlspecialchars($data['data']['audio_error'] ?? 'NULL') ?></strong>
        </li>
        <li>transcript_text: <strong><?= !empty($data['data']['transcript_text']) ? 'EXISTS' : 'NULL' ?></strong></li>
        <li>summary_text: <strong><?= !empty($data['data']['summary_text']) ? 'EXISTS' : 'NULL' ?></strong></li>
    </ul>
    <?php endif; ?>
</body>
</html>
