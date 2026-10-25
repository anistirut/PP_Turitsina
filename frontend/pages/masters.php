<?php

// Страница «Мастера»: список, добавление, изменение, удаление и выбор услуг, которые оказывает мастер.

// Подключаем сессию, БД и нужные классы. Гостей requireAuth() отправляет на вход.
session_start();

require_once __DIR__ . '/../../backend/functions/connectionDB.php';
require_once __DIR__ . '/../../backend/functions/session.php';
require_once __DIR__ . '/../../backend/classes/MasterContext.php';
require_once __DIR__ . '/../../backend/classes/ServiceContext.php';

requireAuth();

$masterContext = new MasterContext($mysqli);
$serviceContext = new ServiceContext($mysqli);

// action: list - таблица, add - форма добавления, edit - форма изменения.
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$errors = [];
$formData = ['last_name' => '', 'first_name' => '', 'phone' => '', 'hire_date' => ''];
$selectedServiceIds = [];

// Удаление: форму с кнопкой «Удалить» отправляет таблица.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $deleteId = (int)($_POST['id'] ?? 0);
    $masterContext->delete($deleteId);
    header('Location: masters.php');
    exit;
}

// Сохранение формы: сначала проверяем поля, потом create или update.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $formData = [
        'last_name' => trim($_POST['last_name'] ?? ''),
        'first_name' => trim($_POST['first_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'hire_date' => trim($_POST['hire_date'] ?? ''),
    ];
    $selectedServiceIds = array_map('intval', $_POST['service_ids'] ?? []);

    if ($formData['last_name'] === '') {
        $errors[] = 'Укажите фамилию.';
    }
    if ($formData['first_name'] === '') {
        $errors[] = 'Укажите имя.';
    }
    if ($formData['phone'] === '') {
        $errors[] = 'Укажите телефон.';
    }
    if ($formData['hire_date'] === '') {
        $errors[] = 'Укажите дату приёма на работу.';
    }

    if (!$errors) {
        try {
            if ($id > 0) {
                $masterContext->update($id, $formData['last_name'], $formData['first_name'], $formData['phone'], $formData['hire_date']);
            } else {
                $id = $masterContext->create($formData['last_name'], $formData['first_name'], $formData['phone'], $formData['hire_date']);
            }

            // Список услуг мастера (флажки в форме) каждый раз записываем заново целиком.
            $masterContext->setServices($id, $selectedServiceIds);

            header('Location: masters.php');
            exit;
        // Код 1062 - нарушена уникальность (мастер с таким телефоном уже есть).
        } catch (mysqli_sql_exception $e) {
            $errors[] = $e->getCode() === 1062
                ? 'Мастер с таким телефоном уже существует.'
                : 'Не удалось сохранить мастера. Попробуйте ещё раз.';
        }
    }

    $action = $id > 0 ? 'edit' : 'add';
}

// Открыли форму изменения - подставляем в неё текущие данные.
if ($action === 'edit' && $id > 0 && !$errors) {
    $master = $masterContext->getById($id);

    if (!$master) {
        header('Location: masters.php');
        exit;
    }

    $formData = [
        'last_name' => $master->last_name,
        'first_name' => $master->first_name,
        'phone' => $master->phone,
        'hire_date' => $master->hire_date,
    ];
    $selectedServiceIds = $masterContext->getServiceIds($id);
}

// Все услуги - для флажков в форме мастера.
$allServices = $serviceContext->getAll();

// Параметры списка: сортировка (только из разрешённых столбцов), номер страницы.
$sort = in_array($_GET['sort'] ?? '', MasterContext::SORTABLE_COLUMNS, true) ? $_GET['sort'] : 'last_name';
$dir = ($_GET['dir'] ?? '') === 'desc' ? 'desc' : 'asc';
$perPage = 5;
$page = max(1, (int)($_GET['page'] ?? 1));

// Всего мастеров считаем отдельно, чтобы знать число страниц; текущую страницу не даём выйти за их пределы.
$totalMasters = ($action === 'list') ? $masterContext->countAll() : 0;
$totalPages = max(1, (int)ceil($totalMasters / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$mastersWithServices = ($action === 'list') ? $masterContext->getAllWithServices($sort, $dir, $perPage, $offset) : [];

// Данные готовы - подключаем шапку и выводим страницу.
$pageTitle = 'Мастера';
$activePage = 'masters';
require_once __DIR__ . '/../elements/head.php';
require_once __DIR__ . '/../elements/siteHeader.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Мастера</h1>
        <?php if ($action === 'list'): ?>
            <div class="header-actions">
                <a class="btn btn-secondary" href="exportExcel.php?entity=masters">Скачать Excel</a>
                <a class="btn" href="masters.php?action=add">Добавить мастера</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($action === 'list'): ?>

        <table>
            <thead>
                <tr>
                    <th><a href="<?= h(queryStringWith(['sort' => 'last_name', 'dir' => nextSortDirection('last_name', $sort, $dir), 'page' => 1])) ?>">Фамилия<?= sortIndicator('last_name', $sort, $dir) ?></a></th>
                    <th><a href="<?= h(queryStringWith(['sort' => 'first_name', 'dir' => nextSortDirection('first_name', $sort, $dir), 'page' => 1])) ?>">Имя<?= sortIndicator('first_name', $sort, $dir) ?></a></th>
                    <th><a href="<?= h(queryStringWith(['sort' => 'phone', 'dir' => nextSortDirection('phone', $sort, $dir), 'page' => 1])) ?>">Телефон<?= sortIndicator('phone', $sort, $dir) ?></a></th>
                    <th><a href="<?= h(queryStringWith(['sort' => 'hire_date', 'dir' => nextSortDirection('hire_date', $sort, $dir), 'page' => 1])) ?>">Дата приёма<?= sortIndicator('hire_date', $sort, $dir) ?></a></th>
                    <th>Услуги</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$mastersWithServices): ?>
                    <tr><td colspan="6">Мастера не найдены.</td></tr>
                <?php endif; ?>
                <?php foreach ($mastersWithServices as $row): ?>
                    <?php $master = $row['master']; ?>
                    <tr>
                        <td><?= h($master->last_name) ?></td>
                        <td><?= h($master->first_name) ?></td>
                        <td><?= h($master->phone) ?></td>
                        <td><?= h($master->hire_date) ?></td>
                        <td><?= h($row['services'] !== '' ? $row['services'] : '—') ?></td>
                        <td class="actions">
                            <a class="btn btn-secondary" href="masters.php?action=edit&id=<?= $master->id ?>">Изменить</a>
                            <form method="post" action="masters.php" onsubmit="return confirm('Удалить мастера?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $master->id ?>">
                                <button type="submit" class="btn btn-danger">Удалить</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php require __DIR__ . '/../elements/pagination.php'; ?>

    <?php else: ?>

        <form class="entity-form" method="post" action="masters.php">
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

            <label for="hire_date">Дата приёма на работу</label>
            <input type="date" id="hire_date" name="hire_date" value="<?= h($formData['hire_date']) ?>" required>

            <label>Оказываемые услуги</label>
            <div class="checkbox-list">
                <?php if (!$allServices): ?>
                    <span class="checkbox-empty">Сначала добавьте услуги в разделе «Услуги».</span>
                <?php endif; ?>
                <?php foreach ($allServices as $service): ?>
                    <label class="checkbox-item">
                        <input
                            type="checkbox"
                            name="service_ids[]"
                            value="<?= $service->id ?>"
                            <?= in_array($service->id, $selectedServiceIds, true) ? 'checked' : '' ?>
                        >
                        <?= h($service->name) ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn"><?= $id > 0 ? 'Сохранить' : 'Добавить' ?></button>
            <a class="btn btn-secondary" href="masters.php">Отмена</a>
        </form>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../elements/footer.php'; ?>
