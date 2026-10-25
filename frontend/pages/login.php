<?php

// Страница входа.

// Подключаем сессию, БД и нужные классы.
session_start();

require_once __DIR__ . '/../../backend/functions/connectionDB.php';
require_once __DIR__ . '/../../backend/functions/session.php';
require_once __DIR__ . '/../../backend/classes/UserContext.php';

// Уже вошедшему форма входа не нужна.
if (isLoggedIn()) {
    header('Location: clients.php');
    exit;
}

$error = '';
$loginValue = '';

// Проверка логина и пароля. В БД хранится хеш, поэтому сравниваем через password_verify().
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginValue = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($loginValue === '' || $password === '') {
        $error = 'Введите логин и пароль.';
    } else {
        $userContext = new UserContext($mysqli);
        $user = $userContext->findByLogin($loginValue);

        if ($user && password_verify($password, $user->password)) {
            loginUser($user);
            header('Location: clients.php');
            exit;
        }

        $error = 'Неверный логин или пароль.';
    }
}

$pageTitle = 'Вход';
// Класс у body включает отдельную вёрстку страниц входа (без шапки сайта).
$bodyClass = 'auth-page';
require_once __DIR__ . '/../elements/head.php';
?>

<div class="auth-layout">
    <aside class="auth-visual">
        <div class="auth-brand">
            <span class="auth-brand-name">Turka</span>
            <span class="auth-brand-sub">Салон красоты</span>
        </div>
    </aside>

    <main class="auth-panel">
        <div class="auth-form-wrap">
            <h1>Вход</h1>
            <p class="auth-lead">Введите данные учётной записи, чтобы продолжить работу.</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="auth-field">
                    <label for="login">Логин</label>
                    <input type="text" id="login" name="login" value="<?= h($loginValue) ?>" autocomplete="username" autofocus required>
                </div>

                <div class="auth-field">
                    <label for="password">Пароль</label>
                    <input type="password" id="password" name="password" autocomplete="current-password" required>
                </div>

                <button type="submit" class="auth-submit">Войти</button>
            </form>

            <p class="auth-hint">Нет учётной записи? <a href="register.php">Зарегистрироваться</a></p>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../elements/footer.php'; ?>
