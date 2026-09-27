<?php

session_start();

require_once __DIR__ . '/../../backend/functions/connectionDB.php';
require_once __DIR__ . '/../../backend/functions/session.php';
require_once __DIR__ . '/../../backend/classes/ReportContext.php';
require_once __DIR__ . '/../../backend/classes/MasterContext.php';
require_once __DIR__ . '/../../backend/classes/ClientContext.php';
require_once __DIR__ . '/../../vendor/autoload.php';

requireAuth();

$reportContext = new ReportContext($mysqli);
$masterContext = new MasterContext($mysqli);
$clientContext = new ClientContext($mysqli);

$reportTitles = [
    'masters_services'    => 'Мастера и услуги',
    'master_schedule'     => 'Расписание мастера',
    'client_history'      => 'История клиента',
    'masters_count'       => 'Записи по мастерам',
    'services_popularity' => 'Популярность услуг',
];

$type = $_GET['type'] ?? 'masters_services';
if (!array_key_exists($type, $reportTitles)) {
    $type = 'masters_services';
}

$masterId = (int)($_GET['master_id'] ?? 0);
$clientId = (int)($_GET['client_id'] ?? 0);
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$columns = [];
$rows = [];
$subtitle = '';

if ($type === 'masters_services') {
    $columns = ['Мастер', 'Услуги'];
    foreach ($reportContext->mastersWithServices() as $row) {
        $rows[] = [$row['name'], $row['services'] ? implode(', ', $row['services']) : '—'];
    }
} elseif ($type === 'master_schedule') {
    $columns = ['Дата и время', 'Клиент', 'Услуга', 'Кабинет', 'Статус'];
    if ($masterId > 0) {
        $master = $masterContext->getById($masterId);
        $subtitle = $master ? 'Мастер: ' . $master->last_name . ' ' . $master->first_name : '';
        foreach ($reportContext->masterSchedule($masterId) as $row) {
            $rows[] = [
                $row['appointment_datetime'],
                $row['client_last_name'] . ' ' . $row['client_first_name'],
                $row['service_name'],
                $row['room_number'],
                $row['status'],
            ];
        }
    }
} elseif ($type === 'client_history') {
    $columns = ['Дата и время', 'Мастер', 'Услуга', 'Кабинет', 'Статус'];
    if ($clientId > 0) {
        $client = $clientContext->getById($clientId);
        $subtitle = $client ? 'Клиент: ' . $client->last_name . ' ' . $client->first_name : '';
        foreach ($reportContext->clientHistory($clientId) as $row) {
            $rows[] = [
                $row['appointment_datetime'],
                $row['master_last_name'] . ' ' . $row['master_first_name'],
                $row['service_name'],
                $row['room_number'],
                $row['status'],
            ];
        }
    }
} elseif ($type === 'masters_count') {
    $columns = ['Мастер', 'Записей'];
    foreach ($reportContext->appointmentsCountByMaster() as $row) {
        $rows[] = [$row['last_name'] . ' ' . $row['first_name'], $row['appointments_count']];
    }
} elseif ($type === 'services_popularity') {
    $columns = ['Услуга', 'Количество'];
    if ($dateFrom !== '' || $dateTo !== '') {
        $subtitle = 'Период: ' . ($dateFrom !== '' ? $dateFrom : '…') . ' — ' . ($dateTo !== '' ? $dateTo : '…');
    }
    foreach ($reportContext->servicePopularity($dateFrom !== '' ? $dateFrom : null, $dateTo !== '' ? $dateTo : null) as $row) {
        $rows[] = [$row['name'], $row['appointments_count']];
    }
}

$html = '<html><head><meta charset="UTF-8"><style>
    body { font-family: "DejaVu Sans", sans-serif; font-size: 12px; color: #262626; }
    h1 { font-size: 16px; margin: 0 0 4px; }
    .subtitle { font-size: 12px; color: #555; margin-bottom: 14px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #999; padding: 5px 8px; text-align: left; }
    th { background-color: #eee; }
</style></head><body>';
$html .= '<h1>Отчёт: ' . htmlspecialchars($reportTitles[$type], ENT_QUOTES, 'UTF-8') . '</h1>';
if ($subtitle !== '') {
    $html .= '<div class="subtitle">' . htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') . '</div>';
}
$html .= '<table><thead><tr>';
foreach ($columns as $column) {
    $html .= '<th>' . htmlspecialchars($column, ENT_QUOTES, 'UTF-8') . '</th>';
}
$html .= '</tr></thead><tbody>';

if (!$rows) {
    $html .= '<tr><td colspan="' . count($columns) . '">Данные не найдены.</td></tr>';
}
foreach ($rows as $row) {
    $html .= '<tr>';
    foreach ($row as $cell) {
        $html .= '<td>' . htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8') . '</td>';
    }
    $html .= '</tr>';
}
$html .= '</tbody></table></body></html>';

$options = new \Dompdf\Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', false);

$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('report_' . $type . '.pdf', ['Attachment' => true]);
