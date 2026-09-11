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
$success = false;
$loginValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginValue = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    $userContext = new UserContext($mysqli);

    if ($loginValue === '' || $password === '' || $passwordConfirm === '') {
        $error = 'Заполните все поля.';
    } elseif (mb_strlen($loginValue) < 3) {
        $error = 'Логин должен содержать не менее 3 символов.';
    } elseif (mb_strlen($password) < 6) {
        $error = 'Пароль должен содержать не менее 6 символов.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Пароли не совпадают.';
    } elseif ($userContext->findByLogin($loginValue) !== null) {
        $error = 'Пользователь с таким логином уже существует.';
    } else {
        $userContext->create($loginValue, password_hash($password, PASSWORD_DEFAULT));
        $success = true;
    }
}

$pageTitle = 'Регистрация';
require_once __DIR__ . '/../elements/head.php';
require_once __DIR__ . '/../elements/siteHeader.php';
?>

<main class="auth-main">
    <div class="auth-box">
        <h1>Регистрация</h1>

        <?php if ($success): ?>
            <div class="alert alert-success">Учётная запись создана. Теперь можно войти.</div>
            <p class="auth-hint"><a href="login.php">Перейти ко входу</a></p>
        <?php else: ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <label for="login">Логин</label>
                <input type="text" id="login" name="login" value="<?= h($loginValue) ?>" autofocus required>

                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" required>

                <label for="password_confirm">Повтор пароля</label>
                <input type="password" id="password_confirm" name="password_confirm" required>

                <button type="submit">Зарегистрироваться</button>
            </form>

            <p class="auth-hint">Уже есть учётная запись? <a href="login.php">Войти</a></p>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../elements/footer.php'; ?>
