<?php

session_start();

require_once __DIR__ . '/../../backend/functions/connectionDB.php';
require_once __DIR__ . '/../../backend/functions/session.php';
require_once __DIR__ . '/../../backend/classes/ReportContext.php';
require_once __DIR__ . '/../../backend/classes/MasterContext.php';
require_once __DIR__ . '/../../backend/classes/ClientContext.php';

requireAuth();

$reportContext = new ReportContext($mysqli);
$masterContext = new MasterContext($mysqli);
$clientContext = new ClientContext($mysqli);

$reportTypes = [
    'masters_services' => 'Мастера и услуги',
    'master_schedule' => 'Расписание мастера',
    'client_history' => 'История клиента',
    'masters_count' => 'Записи по мастерам',
    'services_popularity' => 'Популярность услуг',
];

$type = $_GET['type'] ?? 'masters_services';
if (!array_key_exists($type, $reportTypes)) {
    $type = 'masters_services';
}

$masters = $masterContext->getAll();
$clients = $clientContext->getAll();

$masterId = (int)($_GET['master_id'] ?? 0);
$clientId = (int)($_GET['client_id'] ?? 0);
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$mastersWithServices = [];
$masterSchedule = [];
$clientHistory = [];
$masterCounts = [];
$servicePopularity = [];

if ($type === 'masters_services') {
    $mastersWithServices = $reportContext->mastersWithServices();
} elseif ($type === 'master_schedule' && $masterId > 0) {
    $masterSchedule = $reportContext->masterSchedule($masterId);
} elseif ($type === 'client_history' && $clientId > 0) {
    $clientHistory = $reportContext->clientHistory($clientId);
} elseif ($type === 'masters_count') {
    $masterCounts = $reportContext->appointmentsCountByMaster();
} elseif ($type === 'services_popularity') {
    $servicePopularity = $reportContext->servicePopularity($dateFrom !== '' ? $dateFrom : null, $dateTo !== '' ? $dateTo : null);
}

$pageTitle = 'Отчёты';
$activePage = 'reports';
require_once __DIR__ . '/../elements/head.php';
require_once __DIR__ . '/../elements/siteHeader.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Отчёты</h1>
    </div>

    <nav class="subnav">
        <?php foreach ($reportTypes as $key => $label): ?>
            <a href="reports.php?type=<?= h($key) ?>" class="<?= $type === $key ? 'active' : '' ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($type === 'masters_services'): ?>

        <?php if (!$mastersWithServices): ?>
            <p>Мастера не найдены.</p>
        <?php endif; ?>
        <?php foreach ($mastersWithServices as $row): ?>
            <div class="report-block">
                <div class="report-block-title"><?= h($row['name']) ?></div>
                <?php if ($row['services']): ?>
                    <ul class="report-sublist">
                        <?php foreach ($row['services'] as $serviceName): ?>
                            <li><?= h($serviceName) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="report-empty">Услуги не назначены.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

    <?php elseif ($type === 'master_schedule'): ?>

        <form class="filter-form" method="get" action="">
            <input type="hidden" name="type" value="master_schedule">
            <label for="master_id">Мастер</label>
            <select id="master_id" name="master_id" onchange="this.form.submit()">
                <option value="">— выберите мастера —</option>
                <?php foreach ($masters as $master): ?>
                    <option value="<?= $master->id ?>" <?= $master->id === $masterId ? 'selected' : '' ?>>
                        <?= h($master->last_name . ' ' . $master->first_name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($masterId > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Дата и время</th>
                        <th>Клиент</th>
                        <th>Услуга</th>
                        <th>Кабинет</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$masterSchedule): ?>
                        <tr><td colspan="5">У мастера нет записей.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($masterSchedule as $row): ?>
                        <tr>
                            <td><?= h($row['appointment_date']) ?></td>
                            <td><?= h($row['client_last_name'] . ' ' . $row['client_first_name']) ?></td>
                            <td><?= h($row['service_name']) ?></td>
                            <td><?= h($row['room_number']) ?></td>
                            <td><?= h($row['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    <?php elseif ($type === 'client_history'): ?>

        <form class="filter-form" method="get" action="">
            <input type="hidden" name="type" value="client_history">
            <label for="client_id">Клиент</label>
            <select id="client_id" name="client_id" onchange="this.form.submit()">
                <option value="">— выберите клиента —</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= $client->id ?>" <?= $client->id === $clientId ? 'selected' : '' ?>>
                        <?= h($client->last_name . ' ' . $client->first_name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($clientId > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Дата и время</th>
                        <th>Мастер</th>
                        <th>Услуга</th>
                        <th>Кабинет</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$clientHistory): ?>
                        <tr><td colspan="5">У клиента нет записей.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($clientHistory as $row): ?>
                        <tr>
                            <td><?= h($row['appointment_date']) ?></td>
                            <td><?= h($row['master_last_name'] . ' ' . $row['master_first_name']) ?></td>
                            <td><?= h($row['service_name']) ?></td>
                            <td><?= h($row['room_number']) ?></td>
                            <td><?= h($row['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    <?php elseif ($type === 'masters_count'): ?>

        <table>
            <thead>
                <tr>
                    <th>Мастер</th>
                    <th>Записей</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$masterCounts): ?>
                    <tr><td colspan="2">Мастера не найдены.</td></tr>
                <?php endif; ?>
                <?php foreach ($masterCounts as $row): ?>
                    <tr>
                        <td><?= h($row['last_name'] . ' ' . $row['first_name']) ?></td>
                        <td><?= (int)$row['appointments_count'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php elseif ($type === 'services_popularity'): ?>

        <form class="filter-form" method="get" action="">
            <input type="hidden" name="type" value="services_popularity">
            <label for="date_from">С</label>
            <input type="date" id="date_from" name="date_from" value="<?= h($dateFrom) ?>">
            <label for="date_to">По</label>
            <input type="date" id="date_to" name="date_to" value="<?= h($dateTo) ?>">
            <button type="submit" class="btn btn-secondary">Показать</button>
            <?php if ($dateFrom !== '' || $dateTo !== ''): ?>
                <a class="btn btn-secondary" href="reports.php?type=services_popularity">Сбросить</a>
            <?php endif; ?>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Услуга</th>
                    <th>Количество</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$servicePopularity): ?>
                    <tr><td colspan="2">Данные не найдены.</td></tr>
                <?php endif; ?>
                <?php foreach ($servicePopularity as $row): ?>
                    <tr>
                        <td><?= h($row['name']) ?></td>
                        <td><?= (int)$row['appointments_count'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../elements/footer.php'; ?>
