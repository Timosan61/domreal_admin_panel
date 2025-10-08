<?php
/**
 * API для управления пользователями (CRUD)
 * GET /admin/api/users.php - Список всех пользователей
 * POST /admin/api/users.php - Создать пользователя
 * PUT /admin/api/users.php?id=X - Обновить пользователя
 * DELETE /admin/api/users.php?id=X - Удалить пользователя
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

session_start();
require_once '../../auth/session.php';
require_once '../../auth/csrf.php';
checkAuth(true); // Требуется роль администратора

// CSRF защита для POST/PUT/DELETE запросов
requireCsrfToken();

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(503);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

// GET - Получить список всех пользователей
if ($method === 'GET' && !isset($_GET['id'])) {
    $query = "SELECT
                u.id,
                u.username,
                u.full_name,
                u.employee_id,
                u.role,
                u.is_active,
                u.last_login,
                u.created_at,
                GROUP_CONCAT(ud.department ORDER BY ud.department SEPARATOR ', ') as departments
              FROM users u
              LEFT JOIN user_departments ud ON u.id = ud.user_id
              GROUP BY u.id
              ORDER BY u.created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $users,
        'total' => count($users),
        'csrf_token' => getCsrfToken() // Отправляем токен для последующих запросов
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

// GET - Получить одного пользователя с отделами
if ($method === 'GET' && isset($_GET['id'])) {
    $user_id = intval($_GET['id']);

    $query = "SELECT * FROM users WHERE id = :id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit();
    }

    // Получаем отделы пользователя
    $dept_query = "SELECT department FROM user_departments WHERE user_id = :user_id";
    $dept_stmt = $db->prepare($dept_query);
    $dept_stmt->bindParam(':user_id', $user_id);
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);

    $user['departments'] = $departments;
    unset($user['password_hash']); // Не возвращаем хеш пароля

    echo json_encode([
        'success' => true,
        'data' => $user
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

// POST - Создать нового пользователя
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    $full_name = $data['full_name'] ?? '';
    $role = $data['role'] ?? 'user';
    $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 1;
    $departments = $data['departments'] ?? [];

    // Валидация
    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Username and password are required'
        ]);
        exit();
    }

    // Проверяем уникальность username
    $check_query = "SELECT id FROM users WHERE username = :username LIMIT 1";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':username', $username);
    $check_stmt->execute();

    if ($check_stmt->fetch()) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Username already exists'
        ]);
        exit();
    }

    try {
        // Создаем пользователя
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        $query = "INSERT INTO users (username, password_hash, full_name, role, is_active)
                  VALUES (:username, :password_hash, :full_name, :role, :is_active)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password_hash', $password_hash);
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':is_active', $is_active);

        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to create user in database',
                'sql_error' => $stmt->errorInfo()
            ]);
            exit();
        }

        $user_id = $db->lastInsertId();

        // Добавляем отделы
        if (!empty($departments)) {
            $dept_query = "INSERT INTO user_departments (user_id, department) VALUES (:user_id, :department)";
            $dept_stmt = $db->prepare($dept_query);

            foreach ($departments as $dept) {
                $dept_stmt->bindParam(':user_id', $user_id);
                $dept_stmt->bindParam(':department', $dept);
                if (!$dept_stmt->execute()) {
                    // Если ошибка при добавлении отдела, логируем но продолжаем
                    error_log("Failed to add department '{$dept}' for user {$user_id}: " . json_encode($dept_stmt->errorInfo()));
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'User created successfully',
            'user_id' => $user_id
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Exception: ' . $e->getMessage()
        ]);
    }
    exit();
}

// PUT - Обновить пользователя
if ($method === 'PUT') {
    $user_id = intval($_GET['id'] ?? 0);
    $data = json_decode(file_get_contents("php://input"), true);

    if ($user_id === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID is required']);
        exit();
    }

    $full_name = $data['full_name'] ?? null;
    $role = $data['role'] ?? null;
    $is_active = isset($data['is_active']) ? (int)$data['is_active'] : null;
    $password = $data['password'] ?? null;
    $departments = $data['departments'] ?? null;

    // Обновляем основные данные пользователя
    $updates = [];
    $params = [':id' => $user_id];

    if ($full_name !== null) {
        $updates[] = "full_name = :full_name";
        $params[':full_name'] = $full_name;
    }
    if ($role !== null) {
        $updates[] = "role = :role";
        $params[':role'] = $role;
    }
    if ($is_active !== null) {
        $updates[] = "is_active = :is_active";
        $params[':is_active'] = $is_active;
    }
    if (!empty($password)) {
        $updates[] = "password_hash = :password_hash";
        $params[':password_hash'] = password_hash($password, PASSWORD_BCRYPT);
    }

    try {
        if (!empty($updates)) {
            $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $db->prepare($query);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            if (!$stmt->execute()) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to update user in database',
                    'sql_error' => $stmt->errorInfo()
                ]);
                exit();
            }
        }

        // Обновляем отделы
        if ($departments !== null) {
            // Удаляем старые
            $delete_query = "DELETE FROM user_departments WHERE user_id = :user_id";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(':user_id', $user_id);
            $delete_stmt->execute();

            // Добавляем новые
            if (!empty($departments)) {
                $dept_query = "INSERT INTO user_departments (user_id, department) VALUES (:user_id, :department)";
                $dept_stmt = $db->prepare($dept_query);

                foreach ($departments as $dept) {
                    $dept_stmt->bindParam(':user_id', $user_id);
                    $dept_stmt->bindParam(':department', $dept);
                    if (!$dept_stmt->execute()) {
                        // Логируем ошибку, но продолжаем
                        error_log("Failed to add department '{$dept}' for user {$user_id}: " . json_encode($dept_stmt->errorInfo()));
                    }
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'User updated successfully'
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Exception: ' . $e->getMessage()
        ]);
    }
    exit();
}

// DELETE - Удалить пользователя
if ($method === 'DELETE') {
    $user_id = intval($_GET['id'] ?? 0);

    if ($user_id === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID is required']);
        exit();
    }

    // Проверяем, что не удаляем самого себя
    if ($user_id == $_SESSION['user_id']) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete yourself']);
        exit();
    }

    $query = "DELETE FROM users WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $user_id);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'User deleted successfully'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete user']);
    }
    exit();
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
