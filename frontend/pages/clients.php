<?php

session_start();

require_once __DIR__ . '/../../backend/functions/connectionDB.php';
require_once __DIR__ . '/../../backend/functions/session.php';
require_once __DIR__ . '/../../backend/classes/ClientContext.php';

requireAuth();

$clientContext = new ClientContext($mysqli);

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$errors = [];
$formData = ['last_name' => '', 'first_name' => '', 'phone' => '', 'birth_date' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $deleteId = (int)($_POST['id'] ?? 0);
    $clientContext->delete($deleteId);
    header('Location: clients.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $formData = [
        'last_name' => trim($_POST['last_name'] ?? ''),
        'first_name' => trim($_POST['first_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'birth_date' => trim($_POST['birth_date'] ?? ''),
    ];

    if ($formData['last_name'] === '') {
        $errors[] = 'Укажите фамилию.';
    }
    if ($formData['first_name'] === '') {
        $errors[] = 'Укажите имя.';
    }
    if ($formData['phone'] === '') {
        $errors[] = 'Укажите телефон.';
    }

    if (!$errors) {
        $birthDate = $formData['birth_date'] !== '' ? $formData['birth_date'] : null;

        try {
            if ($id > 0) {
                $clientContext->update($id, $formData['last_name'], $formData['first_name'], $formData['phone'], $birthDate);
            } else {
                $clientContext->create($formData['last_name'], $formData['first_name'], $formData['phone'], $birthDate);
            }

            header('Location: clients.php');
            exit;
        } catch (mysqli_sql_exception $e) {
            $errors[] = $e->getCode() === 1062
                ? 'Клиент с таким телефоном уже существует.'
                : 'Не удалось сохранить клиента. Попробуйте ещё раз.';
        }
    }

    $action = $id > 0 ? 'edit' : 'add';
}

if ($action === 'edit' && $id > 0 && !$errors) {
    $client = $clientContext->getById($id);

    if (!$client) {
        header('Location: clients.php');
        exit;
    }

    $formData = [
        'last_name' => $client->last_name,
        'first_name' => $client->first_name,
        'phone' => $client->phone,
        'birth_date' => $client->birth_date ?? '',
    ];
}

$search = trim($_GET['search'] ?? '');
$clients = ($action === 'list') ? $clientContext->getAll($search) : [];

$pageTitle = 'Клиенты';
$activePage = 'clients';
require_once __DIR__ . '/../elements/head.php';
require_once __DIR__ . '/../elements/siteHeader.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Клиенты</h1>
        <?php if ($action === 'list'): ?>
            <a class="btn" href="clients.php?action=add">Добавить клиента</a>
        <?php endif; ?>
    </div>

    <?php if ($action === 'list'): ?>

        <form class="search-form" method="get" action="">
            <input type="text" name="search" placeholder="Поиск по фамилии" value="<?= h($search) ?>">
            <button type="submit" class="btn btn-secondary">Найти</button>
            <?php if ($search !== ''): ?>
                <a class="btn btn-secondary" href="clients.php">Сбросить</a>
            <?php endif; ?>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Фамилия</th>
                    <th>Имя</th>
                    <th>Телефон</th>
                    <th>Дата рождения</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$clients): ?>
                    <tr><td colspan="5">Клиенты не найдены.</td></tr>
                <?php endif; ?>
                <?php foreach ($clients as $client): ?>
                    <tr>
                        <td><?= h($client->last_name) ?></td>
                        <td><?= h($client->first_name) ?></td>
                        <td><?= h($client->phone) ?></td>
                        <td><?= h($client->birth_date ?? '') ?></td>
                        <td class="actions">
                            <a class="btn btn-secondary" href="clients.php?action=edit&id=<?= $client->id ?>">Изменить</a>
                            <form method="post" action="clients.php" onsubmit="return confirm('Удалить клиента?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $client->id ?>">
                                <button type="submit" class="btn btn-danger">Удалить</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php else: ?>

        <form class="entity-form" method="post" action="clients.php">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $id ?>">

            <?php foreach ($errors as $error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endforeach; ?>

            <label for="last_name">Фамилия</label>
            <input type="text" id="last_name" name="last_name" value="<?= h($formData['last_name']) ?>" required>

            <label for="first_name">Имя</label>
            <input type="text" id="first_name" name="first_name" value="<?= h($formData['first_name']) ?>" required>

            <label for="phone">Телефон</label>
            <input type="text" id="phone" name="phone" value="<?= h($formData['phone']) ?>" required>

            <label for="birth_date">Дата рождения</label>
            <input type="date" id="birth_date" name="birth_date" value="<?= h($formData['birth_date']) ?>">

            <button type="submit" class="btn"><?= $id > 0 ? 'Сохранить' : 'Добавить' ?></button>
            <a class="btn btn-secondary" href="clients.php">Отмена</a>
        </form>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../elements/footer.php'; ?>
