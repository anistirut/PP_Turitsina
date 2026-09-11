<?php

session_start();

require_once __DIR__ . '/../../backend/functions/connectionDB.php';
require_once __DIR__ . '/../../backend/functions/session.php';
require_once __DIR__ . '/../../backend/classes/RoomContext.php';

requireAuth();

$roomContext = new RoomContext($mysqli);

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$errors = [];
$formData = ['number' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $deleteId = (int)($_POST['id'] ?? 0);
    $roomContext->delete($deleteId);
    header('Location: rooms.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $formData = [
        'number' => trim($_POST['number'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
    ];

    if ($formData['number'] === '') {
        $errors[] = 'Укажите номер кабинета.';
    }

    if (!$errors) {
        $description = $formData['description'] !== '' ? $formData['description'] : null;

        try {
            if ($id > 0) {
                $roomContext->update($id, $formData['number'], $description);
            } else {
                $roomContext->create($formData['number'], $description);
            }

            header('Location: rooms.php');
            exit;
        } catch (mysqli_sql_exception $e) {
            $errors[] = $e->getCode() === 1062
                ? 'Кабинет с таким номером уже существует.'
                : 'Не удалось сохранить кабинет. Попробуйте ещё раз.';
        }
    }

    $action = $id > 0 ? 'edit' : 'add';
}

if ($action === 'edit' && $id > 0 && !$errors) {
    $room = $roomContext->getById($id);

    if (!$room) {
        header('Location: rooms.php');
        exit;
    }

    $formData = [
        'number' => $room->number,
        'description' => $room->description ?? '',
    ];
}

$rooms = ($action === 'list') ? $roomContext->getAll() : [];

$pageTitle = 'Кабинеты';
$activePage = 'rooms';
require_once __DIR__ . '/../elements/head.php';
require_once __DIR__ . '/../elements/siteHeader.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Кабинеты</h1>
        <?php if ($action === 'list'): ?>
            <a class="btn" href="rooms.php?action=add">Добавить кабинет</a>
        <?php endif; ?>
    </div>

    <?php if ($action === 'list'): ?>

        <table>
            <thead>
                <tr>
                    <th>Номер</th>
                    <th>Описание</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rooms): ?>
                    <tr><td colspan="3">Кабинеты не найдены.</td></tr>
                <?php endif; ?>
                <?php foreach ($rooms as $room): ?>
                    <tr>
                        <td><?= h($room->number) ?></td>
                        <td><?= h($room->description ?? '') ?></td>
                        <td class="actions">
                            <a class="btn btn-secondary" href="rooms.php?action=edit&id=<?= $room->id ?>">Изменить</a>
                            <form method="post" action="rooms.php" onsubmit="return confirm('Удалить кабинет?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $room->id ?>">
                                <button type="submit" class="btn btn-danger">Удалить</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php else: ?>

        <form class="entity-form" method="post" action="rooms.php">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $id ?>">

            <?php foreach ($errors as $error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endforeach; ?>

            <label for="number">Номер</label>
            <input type="text" id="number" name="number" value="<?= h($formData['number']) ?>" required>

            <label for="description">Описание</label>
            <input type="text" id="description" name="description" value="<?= h($formData['description']) ?>">

            <button type="submit" class="btn"><?= $id > 0 ? 'Сохранить' : 'Добавить' ?></button>
            <a class="btn btn-secondary" href="rooms.php">Отмена</a>
        </form>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../elements/footer.php'; ?>
