<?php

session_start();

require_once __DIR__ . '/../../backend/functions/connectionDB.php';
require_once __DIR__ . '/../../backend/functions/session.php';
require_once __DIR__ . '/../../backend/classes/UserContext.php';

requireAuth();

$userContext = new UserContext($mysqli);
$currentUser = $userContext->getById((int)$_SESSION['user']['id']);

if (!$currentUser) {
    logoutUser();
    header('Location: login.php');
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';

    if ($currentPassword === '' || $newPassword === '' || $newPasswordConfirm === '') {
        $errors[] = 'Заполните все поля.';
    } elseif (!password_verify($currentPassword, $currentUser->password)) {
        $errors[] = 'Текущий пароль указан неверно.';
    } elseif (mb_strlen($newPassword) < 6) {
        $errors[] = 'Новый пароль должен содержать не менее 6 символов.';
    } elseif ($newPassword !== $newPasswordConfirm) {
        $errors[] = 'Новый пароль и подтверждение не совпадают.';
    } else {
        $userContext->updatePassword($currentUser->id, password_hash($newPassword, PASSWORD_DEFAULT));
        $success = true;
    }
}

$pageTitle = 'Профиль';
$activePage = 'profile';
require_once __DIR__ . '/../elements/head.php';
require_once __DIR__ . '/../elements/siteHeader.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Профиль</h1>
    </div>

    <div class="profile-layout">
        <section class="profile-card profile-account">
            <h2>Учётная запись</h2>
            <span class="profile-label">Логин</span>
            <p class="profile-value"><?= h($currentUser->login) ?></p>
        </section>

        <section class="profile-card">
            <h2>Смена пароля</h2>

            <?php if ($success): ?>
                <div class="alert alert-success">Пароль успешно изменён.</div>
            <?php endif; ?>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endforeach; ?>

            <form method="post" action="profile.php">
                <label for="current_password">Текущий пароль</label>
                <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>

                <label for="new_password">Новый пароль</label>
                <input type="password" id="new_password" name="new_password" autocomplete="new-password" required>

                <label for="new_password_confirm">Повтор нового пароля</label>
                <input type="password" id="new_password_confirm" name="new_password_confirm" autocomplete="new-password" required>

                <button type="submit" class="btn">Сменить пароль</button>
            </form>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/../elements/footer.php'; ?>
