<?php
/**
 * Скрипт для создания тестового пользователя
 * Запустить: php admin_panel/create_test_user.php
 */

require_once 'config/database.php';

$username = 'testuser';
$password = 'test123';
$full_name = 'Test User';
$role = 'admin';

// Генерируем хеш пароля
$password_hash = password_hash($password, PASSWORD_BCRYPT);

echo "Создание тестового пользователя...\n";
echo "Логин: $username\n";
echo "Пароль: $password\n";
echo "Хеш: $password_hash\n\n";

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($db === null) {
        die("Ошибка подключения к базе данных\n");
    }

    // Проверяем, существует ли пользователь
    $check_query = "SELECT id FROM users WHERE username = :username";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':username', $username);
    $check_stmt->execute();

    if ($check_stmt->fetch()) {
        echo "Пользователь '$username' уже существует. Обновляем пароль...\n";

        $update_query = "UPDATE users SET password_hash = :password_hash, is_active = 1 WHERE username = :username";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':password_hash', $password_hash);
        $update_stmt->bindParam(':username', $username);

        if ($update_stmt->execute()) {
            echo "✅ Пароль обновлен успешно!\n";
        } else {
            echo "❌ Ошибка при обновлении пароля\n";
        }
    } else {
        echo "Создаем нового пользователя...\n";

        $insert_query = "INSERT INTO users (username, password_hash, full_name, role, is_active)
                         VALUES (:username, :password_hash, :full_name, :role, 1)";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':username', $username);
        $insert_stmt->bindParam(':password_hash', $password_hash);
        $insert_stmt->bindParam(':full_name', $full_name);
        $insert_stmt->bindParam(':role', $role);

        if ($insert_stmt->execute()) {
            echo "✅ Пользователь создан успешно!\n";

            // Добавляем доступ ко всем отделам
            $user_id = $db->lastInsertId();
            $departments_query = "INSERT INTO user_departments (user_id, department)
                                  SELECT $user_id, department FROM employees
                                  WHERE department IS NOT NULL AND department != ''
                                  GROUP BY department";
            $db->exec($departments_query);
            echo "✅ Добавлен доступ ко всем отделам\n";
        } else {
            echo "❌ Ошибка при создании пользователя\n";
        }
    }

    echo "\n🎉 Готово! Используйте эти учетные данные для входа:\n";
    echo "   Логин: $username\n";
    echo "   Пароль: $password\n";

} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
