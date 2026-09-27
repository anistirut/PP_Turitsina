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
$filterClientId = (int)($_GET['filter_client_id'] ?? 0);
$filterMasterId = (int)($_GET['filter_master_id'] ?? 0);
$filterServiceId = (int)($_GET['filter_service_id'] ?? 0);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'quick_status') {
    $quickId = (int)($_POST['id'] ?? 0);
    $quickStatus = $_POST['status'] ?? '';

    if (in_array($quickStatus, ['Завершено', 'Отменено'], true)) {
        $quickAppointment = $appointmentContext->getById($quickId);
        if ($quickAppointment) {
            $appointmentContext->update(
                $quickId,
                $quickAppointment->client_id,
                $quickAppointment->master_id,
                $quickAppointment->service_id,
                $quickAppointment->room_id,
                $quickAppointment->appointment_datetime,
                $quickStatus,
                $quickAppointment->comment
            );
        }
    }

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

    $appointmentDateTime = str_replace('T', ' ', $formData['appointment_date']);
    if (strlen($appointmentDateTime) === 16) {
        $appointmentDateTime .= ':00';
    }

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

    if (!$errors && $formData['master_id'] > 0 && $formData['service_id'] > 0 && $formData['appointment_date'] !== '') {
        $service = $serviceContext->getById($formData['service_id']);

        if ($service) {
            $masterConflict = $appointmentContext->findConflict($formData['master_id'], $appointmentDateTime, $service->duration_minutes, $id);
            if ($masterConflict) {
                $conflictStart = new DateTime($masterConflict['appointment_datetime']);
                $conflictEnd = (clone $conflictStart)->modify('+' . $masterConflict['duration_minutes'] . ' minutes');
                $errors[] = 'Мастер уже занят в это время: с ' . $conflictStart->format('d.m.Y H:i')
                    . ' до ' . $conflictEnd->format('H:i') . '.';
            }

            if ($formData['room_id'] > 0) {
                $roomConflict = $appointmentContext->findRoomConflict($formData['room_id'], $appointmentDateTime, $service->duration_minutes, $id);
                if ($roomConflict) {
                    $conflictStart = new DateTime($roomConflict['appointment_datetime']);
                    $conflictEnd = (clone $conflictStart)->modify('+' . $roomConflict['duration_minutes'] . ' minutes');
                    $errors[] = 'Кабинет уже занят в это время: с ' . $conflictStart->format('d.m.Y H:i')
                        . ' до ' . $conflictEnd->format('H:i') . '.';
                }
            }
        }
    }

    if (!$errors) {
        $comment = $formData['comment'] !== '' ? $formData['comment'] : null;

        try {
            if ($id > 0) {
                $appointmentContext->update(
                    $id,
                    $formData['client_id'],
                    $formData['master_id'],
                    $formData['service_id'],
                    $formData['room_id'],
                    $appointmentDateTime,
                    $formData['status'],
                    $comment
                );
            } else {
                $appointmentContext->create(
                    $formData['client_id'],
                    $formData['master_id'],
                    $formData['service_id'],
                    $formData['room_id'],
                    $appointmentDateTime,
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
        'appointment_date' => str_replace(' ', 'T', substr($appointment->appointment_datetime, 0, 16)),
        'status' => $appointment->status,
        'comment' => $appointment->comment ?? '',
    ];
}

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] !== 'POST' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '')) {
    $formData['appointment_date'] = $_GET['date'] . 'T10:00';
}

$view = ($_GET['view'] ?? 'list') === 'calendar' ? 'calendar' : 'list';
$month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
$monthTimestamp = strtotime($month . '-01');
$calendarYear = (int)date('Y', $monthTimestamp);
$calendarMonth = (int)date('n', $monthTimestamp);
$prevMonth = date('Y-m', strtotime('-1 month', $monthTimestamp));
$nextMonth = date('Y-m', strtotime('+1 month', $monthTimestamp));

$monthNames = [
    1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель', 5 => 'Май', 6 => 'Июнь',
    7 => 'Июль', 8 => 'Август', 9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь',
];
$monthLabel = $monthNames[$calendarMonth] . ' ' . $calendarYear;

$appointmentsByDay = [];
if ($action === 'list' && $view === 'calendar') {
    foreach ($appointmentContext->getForMonth($calendarYear, $calendarMonth) as $row) {
        $day = (int)date('j', strtotime($row['appointment_datetime']));
        $appointmentsByDay[$day][] = $row;
    }
}

$sort = array_key_exists($_GET['sort'] ?? '', AppointmentContext::SORTABLE_COLUMNS) ? $_GET['sort'] : 'date';
$dir = ($_GET['dir'] ?? '') === 'asc' ? 'asc' : 'desc';
$perPage = 5;
$page = max(1, (int)($_GET['page'] ?? 1));

$totalAppointments = ($action === 'list')
    ? $appointmentContext->countWithDetails($filterClientId, $filterMasterId, $filterServiceId)
    : 0;
$totalPages = max(1, (int)ceil($totalAppointments / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$appointments = ($action === 'list')
    ? $appointmentContext->getAllWithDetails($filterClientId, $filterMasterId, $filterServiceId, $sort, $dir, $perPage, $offset)
    : [];
$clients = ($action === 'list' || $action === 'add' || $action === 'edit') ? $clientContext->getAll() : [];
$masters = ($action === 'list' || $action === 'add' || $action === 'edit') ? $masterContext->getAll() : [];
$services = ($action === 'list' || $action === 'add' || $action === 'edit') ? $serviceContext->getAll() : [];
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
            <div class="header-actions">
                <a class="btn btn-secondary" href="exportExcel.php?<?= h(http_build_query([
                    'entity' => 'appointments',
                    'filter_client_id' => $filterClientId ?: null,
                    'filter_master_id' => $filterMasterId ?: null,
                    'filter_service_id' => $filterServiceId ?: null,
                ])) ?>">Скачать Excel</a>
                <a class="btn" href="appointments.php?action=add">Новая запись</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($action === 'list'): ?>

        <nav class="subnav">
            <a href="appointments.php" class="<?= $view === 'list' ? 'active' : '' ?>">Список</a>
            <a href="appointments.php?view=calendar&month=<?= h($month) ?>" class="<?= $view === 'calendar' ? 'active' : '' ?>">Календарь</a>
        </nav>

    <?php endif; ?>

    <?php if ($action === 'list' && $view === 'list'): ?>

        <form class="filter-form" method="get" action="">
            <label for="filter_client_id">Клиент</label>
            <select id="filter_client_id" name="filter_client_id">
                <option value="">Все клиенты</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= $client->id ?>" <?= $client->id === $filterClientId ? 'selected' : '' ?>>
                        <?= h($client->last_name . ' ' . $client->first_name) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="filter_master_id">Мастер</label>
            <select id="filter_master_id" name="filter_master_id">
                <option value="">Все мастера</option>
                <?php foreach ($masters as $master): ?>
                    <option value="<?= $master->id ?>" <?= $master->id === $filterMasterId ? 'selected' : '' ?>>
                        <?= h($master->last_name . ' ' . $master->first_name) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="filter_service_id">Услуга</label>
            <select id="filter_service_id" name="filter_service_id">
                <option value="">Все услуги</option>
                <?php foreach ($services as $service): ?>
                    <option value="<?= $service->id ?>" <?= $service->id === $filterServiceId ? 'selected' : '' ?>>
                        <?= h($service->name) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-secondary">Показать</button>
            <?php if ($filterClientId > 0 || $filterMasterId > 0 || $filterServiceId > 0): ?>
                <a class="btn btn-secondary" href="appointments.php">Сбросить</a>
            <?php endif; ?>
        </form>

        <table>
            <thead>
                <tr>
                    <th><a href="<?= h(queryStringWith(['sort' => 'date', 'dir' => nextSortDirection('date', $sort, $dir), 'page' => 1])) ?>">Дата и время<?= sortIndicator('date', $sort, $dir) ?></a></th>
                    <th><a href="<?= h(queryStringWith(['sort' => 'client', 'dir' => nextSortDirection('client', $sort, $dir), 'page' => 1])) ?>">Клиент<?= sortIndicator('client', $sort, $dir) ?></a></th>
                    <th><a href="<?= h(queryStringWith(['sort' => 'master', 'dir' => nextSortDirection('master', $sort, $dir), 'page' => 1])) ?>">Мастер<?= sortIndicator('master', $sort, $dir) ?></a></th>
                    <th><a href="<?= h(queryStringWith(['sort' => 'service', 'dir' => nextSortDirection('service', $sort, $dir), 'page' => 1])) ?>">Услуга<?= sortIndicator('service', $sort, $dir) ?></a></th>
                    <th><a href="<?= h(queryStringWith(['sort' => 'room', 'dir' => nextSortDirection('room', $sort, $dir), 'page' => 1])) ?>">Кабинет<?= sortIndicator('room', $sort, $dir) ?></a></th>
                    <th><a href="<?= h(queryStringWith(['sort' => 'status', 'dir' => nextSortDirection('status', $sort, $dir), 'page' => 1])) ?>">Статус<?= sortIndicator('status', $sort, $dir) ?></a></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$appointments): ?>
                    <tr><td colspan="7">Записи не найдены.</td></tr>
                <?php endif; ?>
                <?php foreach ($appointments as $row): ?>
                    <tr>
                        <td><?= h($row['appointment_datetime']) ?></td>
                        <td><?= h($row['client_last_name'] . ' ' . $row['client_first_name']) ?></td>
                        <td><?= h($row['master_last_name'] . ' ' . $row['master_first_name']) ?></td>
                        <td><?= h($row['service_name']) ?></td>
                        <td><?= h($row['room_number']) ?></td>
                        <td>
                            <span class="status <?= $statusClasses[$row['status']] ?? '' ?>"><?= h($row['status']) ?></span>
                        </td>
                        <td class="actions">
                            <?php if ($row['status'] === 'Запланировано'): ?>
                                <form method="post" action="appointments.php">
                                    <input type="hidden" name="action" value="quick_status">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <input type="hidden" name="status" value="Завершено">
                                    <button type="submit" class="btn btn-secondary btn-sm">Завершить</button>
                                </form>
                                <form method="post" action="appointments.php">
                                    <input type="hidden" name="action" value="quick_status">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <input type="hidden" name="status" value="Отменено">
                                    <button type="submit" class="btn btn-secondary btn-sm">Отменить</button>
                                </form>
                            <?php endif; ?>
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

        <?php require __DIR__ . '/../elements/pagination.php'; ?>

    <?php elseif ($action === 'list' && $view === 'calendar'): ?>

        <div class="calendar-nav">
            <a class="btn btn-secondary" href="appointments.php?view=calendar&month=<?= h($prevMonth) ?>">&laquo; Пред.</a>
            <span class="calendar-title"><?= h($monthLabel) ?></span>
            <a class="btn btn-secondary" href="appointments.php?view=calendar&month=<?= h($nextMonth) ?>">След. &raquo;</a>
        </div>

        <table class="calendar-table">
            <thead>
                <tr>
                    <th>Пн</th><th>Вт</th><th>Ср</th><th>Чт</th><th>Пт</th><th>Сб</th><th>Вс</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                <?php
                $daysInMonth = (int)date('t', $monthTimestamp);
                $firstWeekday = (int)date('N', $monthTimestamp);
                $column = 0;

                for ($i = 1; $i < $firstWeekday; $i++) {
                    echo '<td class="calendar-empty"></td>';
                    $column++;
                }

                for ($day = 1; $day <= $daysInMonth; $day++) {
                    if ($column === 7) {
                        echo '</tr><tr>';
                        $column = 0;
                    }
                    $column++;
                    $dateStr = sprintf('%04d-%02d-%02d', $calendarYear, $calendarMonth, $day);
                    ?>
                    <td class="calendar-day">
                        <div class="calendar-day-number">
                            <?= $day ?>
                            <a class="calendar-add" href="appointments.php?action=add&date=<?= h($dateStr) ?>" title="Добавить запись на этот день">+</a>
                        </div>
                        <?php foreach ($appointmentsByDay[$day] ?? [] as $appt): ?>
                            <a class="calendar-appt <?= $statusClasses[$appt['status']] ?? '' ?>" href="appointments.php?action=edit&id=<?= $appt['id'] ?>">
                                <?= h(date('H:i', strtotime($appt['appointment_datetime']))) ?>
                                <?= h($appt['client_last_name']) ?> — <?= h($appt['master_last_name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </td>
                    <?php
                }

                while ($column < 7) {
                    echo '<td class="calendar-empty"></td>';
                    $column++;
                }
                ?>
                </tr>
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
