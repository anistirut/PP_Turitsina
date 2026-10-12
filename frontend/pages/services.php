<?php

session_start();

require_once __DIR__ . '/../../backend/functions/connectionDB.php';
require_once __DIR__ . '/../../backend/functions/session.php';
require_once __DIR__ . '/../../backend/classes/ServiceContext.php';

requireAuth();

$serviceContext = new ServiceContext($mysqli);

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$errors = [];
$formData = ['name' => '', 'duration_minutes' => '', 'price' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $deleteId = (int)($_POST['id'] ?? 0);
    $serviceContext->delete($deleteId);
    header('Location: services.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $formData = [
        'name' => trim($_POST['name'] ?? ''),
        'duration_minutes' => trim($_POST['duration_minutes'] ?? ''),
        'price' => trim($_POST['price'] ?? ''),
    ];

    if ($formData['name'] === '') {
        $errors[] = 'Укажите название услуги.';
    }
    if ($formData['duration_minutes'] === '' || !ctype_digit($formData['duration_minutes']) || (int)$formData['duration_minutes'] <= 0) {
        $errors[] = 'Длительность должна быть целым числом минут больше нуля.';
    }
    if ($formData['price'] === '' || !is_numeric($formData['price']) || (float)$formData['price'] < 0) {
        $errors[] = 'Стоимость должна быть неотрицательным числом.';
    }

    if (!$errors) {
        $durationMinutes = (int)$formData['duration_minutes'];
        $price = (float)$formData['price'];

        try {
            if ($id > 0) {
                $serviceContext->update($id, $formData['name'], $durationMinutes, $price);
            } else {
                $serviceContext->create($formData['name'], $durationMinutes, $price);
            }

            header('Location: services.php');
            exit;
        } catch (mysqli_sql_exception $e) {
            $errors[] = 'Не удалось сохранить услугу. Попробуйте ещё раз.';
        }
    }

    $action = $id > 0 ? 'edit' : 'add';
}

if ($action === 'edit' && $id > 0 && !$errors) {
    $service = $serviceContext->getById($id);

    if (!$service) {
        header('Location: services.php');
        exit;
    }

    $formData = [
        'name'             => $service->name,
        'duration_minutes' => (string)$service->duration_minutes,
        'price'            => (string)$service->price,
    ];
}

$sort = in_array($_GET['sort'] ?? '', ServiceContext::SORTABLE_COLUMNS, true) ? $_GET['sort'] : 'name';
$dir = ($_GET['dir'] ?? '') === 'desc' ? 'desc' : 'asc';
$perPage = 5;
$page = max(1, (int)($_GET['page'] ?? 1));

$search = trim($_GET['search'] ?? '');

$totalServices = ($action === 'list') ? $serviceContext->countAll($search) : 0;
$totalPages = max(1, (int)ceil($totalServices / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$services = ($action === 'list') ? $serviceContext->getAll($search, $sort, $dir, $perPage, $offset) : [];

$pageTitle = 'Услуги';
$activePage = 'services';
require_once __DIR__ . '/../elements/head.php';
require_once __DIR__ . '/../elements/siteHeader.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Услуги</h1>
        <?php if ($action === 'list'): ?>
            <div class="header-actions">
                <a class="btn btn-secondary" href="exportExcel.php?entity=services">Скачать Excel</a>
                <a class="btn" href="services.php?action=add">Добавить услугу</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($action === 'list'): ?>

        <form class="search-form" method="get" action="" data-live-form>
            <input type="search" name="search" placeholder="Поиск по названию" value="<?= h($search) ?>" autocomplete="off">
        </form>

        <div id="live-results">
        <table>
            <thead>
                <tr>
                    <th><a href="<?= h(queryStringWith(['sort' => 'name', 'dir' => nextSortDirection('name', $sort, $dir), 'page' => 1])) ?>">Название<?= sortIndicator('name', $sort, $dir) ?></a></th>
                    <th><a href="<?= h(queryStringWith(['sort' => 'duration_minutes', 'dir' => nextSortDirection('duration_minutes', $sort, $dir), 'page' => 1])) ?>">Длительность, мин<?= sortIndicator('duration_minutes', $sort, $dir) ?></a></th>
                    <th><a href="<?= h(queryStringWith(['sort' => 'price', 'dir' => nextSortDirection('price', $sort, $dir), 'page' => 1])) ?>">Стоимость, ₽<?= sortIndicator('price', $sort, $dir) ?></a></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$services): ?>
                    <tr><td colspan="4">Услуги не найдены.</td></tr>
                <?php endif; ?>
                <?php foreach ($services as $service): ?>
                    <tr>
                        <td><?= h($service->name) ?></td>
                        <td><?= (int)$service->duration_minutes ?></td>
                        <td><?= number_format($service->price, 2, '.', ' ') ?></td>
                        <td class="actions">
                            <a class="btn btn-secondary" href="services.php?action=edit&id=<?= $service->id ?>">Изменить</a>
                            <form method="post" action="services.php" onsubmit="return confirm('Удалить услугу?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $service->id ?>">
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

        <form class="entity-form" method="post" action="services.php">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $id ?>">

            <?php foreach ($errors as $error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endforeach; ?>

            <label for="name">Название</label>
            <input type="text" id="name" name="name" value="<?= h($formData['name']) ?>" required>

            <label for="duration_minutes">Длительность, мин</label>
            <input type="number" id="duration_minutes" name="duration_minutes" min="1" step="1" value="<?= h($formData['duration_minutes']) ?>" required>

            <label for="price">Стоимость, ₽</label>
            <input type="number" id="price" name="price" min="0" step="0.01" value="<?= h($formData['price']) ?>" required>

            <button type="submit" class="btn"><?= $id > 0 ? 'Сохранить' : 'Добавить' ?></button>
            <a class="btn btn-secondary" href="services.php">Отмена</a>
        </form>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../elements/footer.php'; ?>
