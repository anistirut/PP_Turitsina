<?php

session_start();

require_once __DIR__ . '/../../backend/functions/connectionDB.php';
require_once __DIR__ . '/../../backend/functions/session.php';
require_once __DIR__ . '/../../backend/classes/AppointmentContext.php';
require_once __DIR__ . '/../../backend/classes/ClientContext.php';
require_once __DIR__ . '/../../backend/classes/MasterContext.php';
require_once __DIR__ . '/../../backend/classes/ServiceContext.php';
require_once __DIR__ . '/../../backend/classes/RoomContext.php';

requireAuth();

$statuses = ['Запланировано', 'Завершено', 'Отменено'];

$appointmentContext = new AppointmentContext($mysqli);
$clientContext = new ClientContext($mysqli);
$masterContext = new MasterContext($mysqli);
$serviceContext = new ServiceContext($mysqli);
$roomContext = new RoomContext($mysqli);

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$errors = [];
$formData = [
    'client_id' => '',
    'master_id' => '',
    'service_id' => '',
    'room_id' => '',
    'appointment_date' => '',
    'status' => 'Запланировано',
    'comment' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $deleteId = (int)($_POST['id'] ?? 0);
    $appointmentContext->delete($deleteId);
    header('Location: appointments.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $formData = [
        'client_id' => (int)($_POST['client_id'] ?? 0),
        'master_id' => (int)($_POST['master_id'] ?? 0),
        'service_id' => (int)($_POST['service_id'] ?? 0),
        'room_id' => (int)($_POST['room_id'] ?? 0),
        'appointment_date' => trim($_POST['appointment_date'] ?? ''),
        'status' => trim($_POST['status'] ?? ''),
        'comment' => trim($_POST['comment'] ?? ''),
    ];

    if ($formData['client_id'] <= 0) {
        $errors[] = 'Выберите клиента.';
    }
    if ($formData['master_id'] <= 0) {
        $errors[] = 'Выберите мастера.';
    }
    if ($formData['service_id'] <= 0) {
        $errors[] = 'Выберите услугу.';
    }
    if ($formData['room_id'] <= 0) {
        $errors[] = 'Выберите кабинет.';
    }
    if ($formData['appointment_date'] === '') {
        $errors[] = 'Укажите дату и время записи.';
    }
    if (!in_array($formData['status'], $statuses, true)) {
        $errors[] = 'Выберите корректный статус.';
    }
    if ($formData['master_id'] > 0 && $formData['service_id'] > 0
        && !in_array($formData['service_id'], $masterContext->getServiceIds($formData['master_id']), true)
    ) {
        $errors[] = 'Выбранный мастер не оказывает эту услугу.';
    }

    if (!$errors) {
        $appointmentDate = str_replace('T', ' ', $formData['appointment_date']);
        if (strlen($appointmentDate) === 16) {
            $appointmentDate .= ':00';
        }
        $comment = $formData['comment'] !== '' ? $formData['comment'] : null;

        try {
            if ($id > 0) {
                $appointmentContext->update(
                    $id,
                    $formData['client_id'],
                    $formData['master_id'],
                    $formData['service_id'],
                    $formData['room_id'],
                    $appointmentDate,
                    $formData['status'],
                    $comment
                );
            } else {
                $appointmentContext->create(
                    $formData['client_id'],
                    $formData['master_id'],
                    $formData['service_id'],
                    $formData['room_id'],
                    $appointmentDate,
                    $formData['status'],
                    $comment
                );
            }

            header('Location: appointments.php');
            exit;
        } catch (mysqli_sql_exception $e) {
            $errors[] = 'Не удалось сохранить запись. Проверьте введённые данные и попробуйте ещё раз.';
        }
    }

    $action = $id > 0 ? 'edit' : 'add';
}

if ($action === 'edit' && $id > 0 && !$errors) {
    $appointment = $appointmentContext->getById($id);

    if (!$appointment) {
        header('Location: appointments.php');
        exit;
    }

    $formData = [
        'client_id' => $appointment->client_id,
        'master_id' => $appointment->master_id,
        'service_id' => $appointment->service_id,
        'room_id' => $appointment->room_id,
        'appointment_date' => str_replace(' ', 'T', substr($appointment->appointment_date, 0, 16)),
        'status' => $appointment->status,
        'comment' => $appointment->comment ?? '',
    ];
}

$appointments = ($action === 'list') ? $appointmentContext->getAllWithDetails() : [];
$clients = ($action === 'add' || $action === 'edit') ? $clientContext->getAll() : [];
$masters = ($action === 'add' || $action === 'edit') ? $masterContext->getAll() : [];
$services = ($action === 'add' || $action === 'edit') ? $serviceContext->getAll() : [];
$rooms = ($action === 'add' || $action === 'edit') ? $roomContext->getAll() : [];
$masterServiceMap = ($action === 'add' || $action === 'edit') ? $masterContext->getAllServiceIds() : [];

$statusClasses = [
    'Запланировано' => 'status-planned',
    'Завершено' => 'status-done',
    'Отменено' => 'status-cancelled',
];

$pageTitle = 'Записи';
$activePage = 'appointments';
require_once __DIR__ . '/../elements/head.php';
require_once __DIR__ . '/../elements/siteHeader.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Записи</h1>
        <?php if ($action === 'list'): ?>
            <a class="btn" href="appointments.php?action=add">Новая запись</a>
        <?php endif; ?>
    </div>

    <?php if ($action === 'list'): ?>

        <table>
            <thead>
                <tr>
                    <th>Дата и время</th>
                    <th>Клиент</th>
                    <th>Мастер</th>
                    <th>Услуга</th>
                    <th>Кабинет</th>
                    <th>Статус</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$appointments): ?>
                    <tr><td colspan="7">Записи не найдены.</td></tr>
                <?php endif; ?>
                <?php foreach ($appointments as $row): ?>
                    <tr>
                        <td><?= h($row['appointment_date']) ?></td>
                        <td><?= h($row['client_last_name'] . ' ' . $row['client_first_name']) ?></td>
                        <td><?= h($row['master_last_name'] . ' ' . $row['master_first_name']) ?></td>
                        <td><?= h($row['service_name']) ?></td>
                        <td><?= h($row['room_number']) ?></td>
                        <td>
                            <span class="status <?= $statusClasses[$row['status']] ?? '' ?>"><?= h($row['status']) ?></span>
                        </td>
                        <td class="actions">
                            <a class="btn btn-secondary" href="appointments.php?action=edit&id=<?= $row['id'] ?>">Изменить</a>
                            <form method="post" action="appointments.php" onsubmit="return confirm('Удалить запись?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <button type="submit" class="btn btn-danger">Удалить</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php else: ?>

        <form class="entity-form" method="post" action="appointments.php">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $id ?>">

            <?php foreach ($errors as $error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endforeach; ?>

            <label for="client_id">Клиент</label>
            <select id="client_id" name="client_id" required>
                <option value="">— выберите —</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= $client->id ?>" <?= $client->id === $formData['client_id'] ? 'selected' : '' ?>>
                        <?= h($client->last_name . ' ' . $client->first_name) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="master_id">Мастер</label>
            <select id="master_id" name="master_id" required>
                <option value="">— выберите —</option>
                <?php foreach ($masters as $master): ?>
                    <option value="<?= $master->id ?>" <?= $master->id === $formData['master_id'] ? 'selected' : '' ?>>
                        <?= h($master->last_name . ' ' . $master->first_name) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="service_id">Услуга</label>
            <select id="service_id" name="service_id" required>
                <option value="">— выберите —</option>
                <?php foreach ($services as $service): ?>
                    <option value="<?= $service->id ?>" <?= $service->id === $formData['service_id'] ? 'selected' : '' ?>>
                        <?= h($service->name) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="room_id">Кабинет</label>
            <select id="room_id" name="room_id" required>
                <option value="">— выберите —</option>
                <?php foreach ($rooms as $room): ?>
                    <option value="<?= $room->id ?>" <?= $room->id === $formData['room_id'] ? 'selected' : '' ?>>
                        <?= h($room->number) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="appointment_date">Дата и время</label>
            <input type="datetime-local" id="appointment_date" name="appointment_date" value="<?= h($formData['appointment_date']) ?>" required>

            <label for="status">Статус</label>
            <select id="status" name="status" required>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= h($status) ?>" <?= $status === $formData['status'] ? 'selected' : '' ?>><?= h($status) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="comment">Комментарий</label>
            <textarea id="comment" name="comment" rows="3"><?= h($formData['comment']) ?></textarea>

            <button type="submit" class="btn"><?= $id > 0 ? 'Сохранить' : 'Создать' ?></button>
            <a class="btn btn-secondary" href="appointments.php">Отмена</a>
        </form>

        <script>
            (function () {
                var masterServices = <?= json_encode($masterServiceMap) ?>;
                var masterSelect = document.getElementById('master_id');
                var serviceSelect = document.getElementById('service_id');

                function filterServices() {
                    var hasMaster = masterSelect.value !== '';
                    var allowed = hasMaster ? (masterServices[masterSelect.value] || []) : null;
                    var previousValue = serviceSelect.value;
                    var previousStillAllowed = false;

                    Array.prototype.forEach.call(serviceSelect.options, function (option) {
                        if (option.value === '') {
                            return;
                        }
                        var isAllowed = !allowed || allowed.indexOf(parseInt(option.value, 10)) !== -1;
                        option.hidden = !isAllowed;
                        option.disabled = !isAllowed;
                        if (isAllowed && option.value === previousValue) {
                            previousStillAllowed = true;
                        }
                    });

                    if (!previousStillAllowed) {
                        serviceSelect.value = '';
                    }
                }

                masterSelect.addEventListener('change', filterServices);
                filterServices();
            })();
        </script>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../elements/footer.php'; ?>
