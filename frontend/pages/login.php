<?php

session_start();

require_once __DIR__ . '/../../backend/functions/connectionDB.php';
require_once __DIR__ . '/../../backend/functions/session.php';
require_once __DIR__ . '/../../backend/classes/UserContext.php';

if (isLoggedIn()) {
    header('Location: clients.php');
    exit;
}

$error = '';
$loginValue = '';

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
require_once __DIR__ . '/../elements/head.php';
require_once __DIR__ . '/../elements/siteHeader.php';
?>

<main class="auth-main">
    <div class="auth-box">
        <h1>Вход в систему</h1>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <label for="login">Логин</label>
            <input type="text" id="login" name="login" value="<?= h($loginValue) ?>" autofocus required>

            <label for="password">Пароль</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Войти</button>
        </form>

        <p class="auth-hint">Нет учётной записи? <a href="register.php">Зарегистрироваться</a></p>
    </div>
</main>

<?php require_once __DIR__ . '/../elements/footer.php'; ?>
