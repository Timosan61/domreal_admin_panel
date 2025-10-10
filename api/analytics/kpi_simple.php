<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

session_start();
require_once '../../auth/session.php';
checkAuth();

// Простой статический ответ для теста
$response = [
    'success' => true,
    'data' => [
        'total_calls' => 3243,
        'analyzed_calls' => 3100,
        'successful_calls' => 1454,
        'first_calls' => 463,
        'unique_clients' => 800,
        'conversion_rate' => 44.8,
        'avg_script_score' => 0.31
    ],
    'filters' => [
        'date_from' => '2025-10-01',
        'date_to' => '2025-10-08',
        'departments' => '',
        'managers' => ''
    ]
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
