<?php
/**
 * Middleware для проверки аутентификации
 * Подключать в начале каждой защищенной страницы
 */

// Проверяем, что сессия уже запущена
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Проверка авторизации пользователя
 * @param bool $require_admin - Требуется ли роль администратора
 * @param bool $is_api - Является ли запрос API (вернуть JSON вместо редиректа)
 * @return array|null - Данные пользователя или null
 */
function checkAuth($require_admin = false, $is_api = false) {
    // Проверяем наличие user_id в сессии
    if (!isset($_SESSION['user_id'])) {
        // Не авторизован
        if ($is_api) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Не авторизован']);
            exit();
        } else {
            header('Location: /auth/login.php');
            exit();
        }
    }

    // Если требуется админ роль
    if ($require_admin && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
        if ($is_api) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Доступ запрещен. Требуются права администратора.']);
            exit();
        } else {
            http_response_code(403);
            die('Доступ запрещен. Требуются права администратора.');
        }
    }

    // Подключаемся к БД для проверки актуальности сессии
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    if ($db === null) {
        die('Ошибка подключения к базе данных');
    }

    $session_id = session_id();

    // Проверяем сессию в БД
    $query = "SELECT s.*, u.username, u.full_name, u.role, u.is_active
              FROM sessions s
              JOIN users u ON s.user_id = u.id
              WHERE s.session_id = :session_id
              AND s.expires_at > NOW()
              AND u.is_active = 1
              LIMIT 1";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':session_id', $session_id);
    $stmt->execute();

    $session_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session_data) {
        // Сессия истекла или пользователь деактивирован
        session_destroy();
        if ($is_api) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Сессия истекла']);
            exit();
        } else {
            header('Location: /auth/login.php?error=session_expired');
            exit();
        }
    }

    // Обновляем last_activity_at
    $update_query = "UPDATE sessions SET last_activity_at = NOW() WHERE session_id = :session_id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':session_id', $session_id);
    $update_stmt->execute();

    // Обновляем данные сессии
    $_SESSION['user_id'] = $session_data['user_id'];
    $_SESSION['username'] = $session_data['username'];
    $_SESSION['full_name'] = $session_data['full_name'] ?? $session_data['username'];
    $_SESSION['role'] = $session_data['role'];

    return $session_data;
}

/**
 * Получить список отделов, доступных текущему пользователю
 * @return array - Массив названий отделов
 */
function getUserDepartments() {
    if (!isset($_SESSION['user_id'])) {
        return [];
    }

    // Если admin - возвращаем все отделы
    if ($_SESSION['role'] === 'admin') {
        require_once __DIR__ . '/../config/database.php';
        $database = new Database();
        $db = $database->getConnection();

        $query = "SELECT DISTINCT department
                  FROM employees
                  WHERE department IS NOT NULL AND department != ''
                  ORDER BY department";
        $stmt = $db->prepare($query);
        $stmt->execute();

        $departments = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $departments[] = $row['department'];
        }
        return $departments;
    }

    // Для обычных пользователей - получаем назначенные отделы
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT department
              FROM user_departments
              WHERE user_id = :user_id
              ORDER BY department";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();

    $departments = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $departments[] = $row['department'];
    }

    return $departments;
}

/**
 * Проверить, есть ли у пользователя доступ к отделу
 * @param string $department - Название отдела
 * @return bool
 */
function hasAccessToDepartment($department) {
    if ($_SESSION['role'] === 'admin') {
        return true;
    }

    $departments = getUserDepartments();
    return in_array($department, $departments);
}
