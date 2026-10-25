<?php

// Страница «Кабинеты»: список с поиском, добавление, изменение, удаление.

// Подключаем сессию, БД и нужные классы. Гостей requireAuth() отправляет на вход.
session_start();

require_once __DIR__ . '/../../backend/functions/connectionDB.php';
require_once __DIR__ . '/../../backend/functions/session.php';
require_once __DIR__ . '/../../backend/classes/RoomContext.php';

requireAuth();

$roomContext = new RoomContext($mysqli);

// action: list - таблица, add - форма добавления, edit - форма изменения.
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$errors = [];
$formData = ['number' => '', 'description' => ''];

// Удаление: форму с кнопкой «Удалить» отправляет таблица.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $deleteId = (int)($_POST['id'] ?? 0);
    $roomContext->delete($deleteId);
    header('Location: rooms.php');
    exit;
}

// Сохранение формы: сначала проверяем поля, потом create или update.
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
        // Код 1062 - нарушена уникальность (кабинет с таким номером уже есть).
        } catch (mysqli_sql_exception $e) {
            $errors[] = $e->getCode() === 1062
                ? 'Кабинет с таким номером уже существует.'
                : 'Не удалось сохранить кабинет. Попробуйте ещё раз.';
        }
    }

    $action = $id > 0 ? 'edit' : 'add';
}

// Открыли форму изменения - подставляем в неё текущие данные.
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

// Параметры списка: сортировка (только из разрешённых столбцов), номер страницы.
$sort = in_array($_GET['sort'] ?? '', RoomContext::SORTABLE_COLUMNS, true) ? $_GET['sort'] : 'number';
$dir = ($_GET['dir'] ?? '') === 'desc' ? 'desc' : 'asc';
$perPage = 5;
$page = max(1, (int)($_GET['page'] ?? 1));

// Поиск - по номеру и описанию кабинета.
$search = trim($_GET['search'] ?? '');

// Всего кабинетов считаем отдельно, чтобы знать число страниц; текущую страницу не даём выйти за их пределы.
$totalRooms = ($action === 'list') ? $roomContext->countAll($search) : 0;
$totalPages = max(1, (int)ceil($totalRooms / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$rooms = ($action === 'list') ? $roomContext->getAll($search, $sort, $dir, $perPage, $offset) : [];

// Данные готовы - подключаем шапку и выводим страницу.
$pageTitle = 'Кабинеты';
$activePage = 'rooms';
require_once __DIR__ . '/../elements/head.php';
require_once __DIR__ . '/../elements/siteHeader.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Кабинеты</h1>
        <?php if ($action === 'list'): ?>
            <div class="header-actions">
                <a class="btn btn-secondary" href="exportExcel.php?entity=rooms">Скачать Excel</a>
                <a class="btn" href="rooms.php?action=add">Добавить кабинет</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($action === 'list'): ?>

        <form class="search-form" method="get" action="" data-live-form>
            <input type="search" name="search" placeholder="Поиск по номеру или описанию" value="<?= h($search) ?>" autocomplete="off">
        </form>

        <div id="live-results">
        <table>
            <thead>
                <tr>
                    <th><a href="<?= h(queryStringWith(['sort' => 'number', 'dir' => nextSortDirection('number', $sort, $dir), 'page' => 1])) ?>">Номер<?= sortIndicator('number', $sort, $dir) ?></a></th>
                    <th><a href="<?= h(queryStringWith(['sort' => 'description', 'dir' => nextSortDirection('description', $sort, $dir), 'page' => 1])) ?>">Описание<?= sortIndicator('description', $sort, $dir) ?></a></th>
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

        <?php require __DIR__ . '/../elements/pagination.php'; ?>
        </div>

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
