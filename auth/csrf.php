<?php
/**
 * CSRF Protection Helper Functions
 * Защита от Cross-Site Request Forgery атак
 */

/**
 * Генерирует CSRF токен и сохраняет в сессии
 * @return string CSRF токен
 */
function generateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Получает текущий CSRF токен из сессии
 * @return string|null CSRF токен или null
 */
function getCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return $_SESSION['csrf_token'] ?? null;
}

/**
 * Валидирует CSRF токен
 * @param string $token Токен для проверки
 * @return bool True если токен валиден
 */
function validateCsrfToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $sessionToken = $_SESSION['csrf_token'] ?? null;

    if ($sessionToken === null || $token === null) {
        return false;
    }

    // Используем hash_equals для защиты от timing attacks
    return hash_equals($sessionToken, $token);
}

/**
 * Проверяет CSRF токен из POST/PUT/DELETE запроса
 * Завершает выполнение с ошибкой 403 если токен невалиден
 */
function requireCsrfToken() {
    $method = $_SERVER['REQUEST_METHOD'];

    // CSRF проверка только для изменяющих запросов
    if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
        return;
    }

    // Получаем токен из разных источников
    $token = null;

    // 1. Из POST данных
    if (isset($_POST['csrf_token'])) {
        $token = $_POST['csrf_token'];
    }
    // 2. Из JSON body
    elseif ($method !== 'GET') {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if (isset($data['csrf_token'])) {
            $token = $data['csrf_token'];
        }
    }
    // 3. Из заголовка X-CSRF-Token
    elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    }

    if (!validateCsrfToken($token)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'error' => 'CSRF token validation failed',
            'message' => 'Invalid or missing CSRF token'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

/**
 * Выводит hidden input с CSRF токеном для формы
 */
function csrfTokenInput() {
    $token = generateCsrfToken();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Получает CSRF токен в виде meta тега для JavaScript
 */
function csrfTokenMeta() {
    $token = generateCsrfToken();
    echo '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
}
