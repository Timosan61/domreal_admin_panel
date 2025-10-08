<?php
/**
 * Страница входа в систему
 */
session_start();
require_once 'csrf.php';

// Если уже авторизован - редирект на главную
if (isset($_SESSION['user_id'])) {
    header('Location: ../index_new.php');
    exit();
}

$error = '';

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка CSRF токена
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf_token)) {
        $error = 'Ошибка безопасности. Попробуйте снова.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Введите логин и пароль';
        } else {
            // Подключаемся к БД
            require_once '../config/database.php';
            $database = new Database();
            $db = $database->getConnection();

            if ($db === null) {
                $error = 'Ошибка подключения к базе данных';
            } else {
                // ЗАЩИТА ОТ БРУТФОРСА: Проверяем количество неудачных попыток
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $check_attempts_query = "SELECT COUNT(*) as attempts
                                         FROM login_attempts
                                         WHERE ip_address = :ip
                                         AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
                $check_stmt = $db->prepare($check_attempts_query);
                $check_stmt->bindParam(':ip', $ip_address);
                $check_stmt->execute();
                $attempts_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
                $failed_attempts = $attempts_data['attempts'] ?? 0;

                // Если больше 5 попыток за 15 минут - блокируем
                if ($failed_attempts >= 5) {
                    $error = 'Слишком много неудачных попыток входа. Попробуйте через 15 минут.';
                    sleep(3); // Дополнительная задержка
                } else {
                    // Проверяем пользователя
                    $query = "SELECT id, username, password_hash, full_name, role, is_active
                              FROM users
                              WHERE username = :username AND is_active = 1
                              LIMIT 1";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':username', $username);
                    $stmt->execute();

                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($user && password_verify($password, $user['password_hash'])) {
                        // Успешная авторизация
                        // Удаляем все неудачные попытки для этого IP
                        $clear_attempts_query = "DELETE FROM login_attempts WHERE ip_address = :ip";
                        $clear_stmt = $db->prepare($clear_attempts_query);
                        $clear_stmt->bindParam(':ip', $ip_address);
                        $clear_stmt->execute();

                        // Регенерируем session ID для защиты от session fixation
                        session_regenerate_id(true);

                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
                        $_SESSION['role'] = $user['role'];

                        // Генерируем новый CSRF токен после авторизации
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                        // Обновляем last_login
                        $update_query = "UPDATE users SET last_login = NOW() WHERE id = :id";
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->bindParam(':id', $user['id']);
                        $update_stmt->execute();

                        // Создаем запись в таблице sessions
                        $session_id = session_id();
                        $expires_at = date('Y-m-d H:i:s', time() + 86400); // +24 часа
                        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
                        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

                        $session_query = "INSERT INTO sessions (session_id, user_id, expires_at, ip_address, user_agent)
                                          VALUES (:session_id, :user_id, :expires_at, :ip_address, :user_agent)
                                          ON DUPLICATE KEY UPDATE
                                          expires_at = :expires_at,
                                          last_activity = NOW()";
                        $session_stmt = $db->prepare($session_query);
                        $session_stmt->bindParam(':session_id', $session_id);
                        $session_stmt->bindParam(':user_id', $user['id']);
                        $session_stmt->bindParam(':expires_at', $expires_at);
                        $session_stmt->bindParam(':ip_address', $ip_address);
                        $session_stmt->bindParam(':user_agent', $user_agent);
                        $session_stmt->execute();

                        // Редирект на главную страницу
                        header('Location: ../index_new.php');
                        exit();
                    } else {
                        // Неудачная попытка - логируем
                        $log_query = "INSERT INTO login_attempts (username, ip_address, attempted_at, user_agent)
                                      VALUES (:username, :ip, NOW(), :user_agent)";
                        $log_stmt = $db->prepare($log_query);
                        $log_stmt->bindParam(':username', $username);
                        $log_stmt->bindParam(':ip', $ip_address);
                        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
                        $log_stmt->bindParam(':user_agent', $user_agent);
                        $log_stmt->execute();

                        $error = 'Неверный логин или пароль';

                        // Прогрессивная задержка в зависимости от количества попыток
                        if ($failed_attempts >= 3) {
                            sleep(2);
                        } elseif ($failed_attempts >= 2) {
                            sleep(1);
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему - Domreal Whisper</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 24px;
        }

        .login-header p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
        }

        .login-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Domreal Whisper</h1>
            <p>Система анализа звонков</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php csrfTokenInput(); ?>

            <div class="form-group">
                <label for="username">Логин</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    required
                    autofocus
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label for="password">Пароль</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                >
            </div>

            <button type="submit" class="btn-login">Войти</button>
        </form>

        <div class="login-footer">
            &copy; 2025 Domreal. Все права защищены.
        </div>
    </div>
</body>
</html>
