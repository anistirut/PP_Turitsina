<?php

// Отчёт в PDF. Принимает те же параметры, что и страница отчётов (type, master_id, client_id, даты).

// Подключаем сессию, БД и нужные классы. Гостей requireAuth() отправляет на вход.
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

// Названия отчётов для заголовка документа.
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

// Любой отчёт сводим к одному виду: заголовки столбцов, строки и подпись.
// Дальше таблица для PDF строится одинаково для всех отчётов.
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

// Короткая обёртка над htmlspecialchars для вывода в HTML.
$e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

// В этих отчётах последний столбец - число, выравниваем его вправо.
$numericLast = in_array($type, ['masters_count', 'services_popularity'], true);
// Цвет текста для статусов записей.
$statusClasses = [
    'Запланировано' => 'st-planned',
    'Завершено' => 'st-done',
    'Отменено' => 'st-cancelled',
];

// Стили для Dompdf. Он понимает не весь CSS (нет flex, переменных), поэтому всё задано напрямую.
$css = '
    @page { margin: 0; }
    body { margin: 0; font-family: "DejaVu Sans", sans-serif; font-size: 10.5px; color: #231820; }
    .band { background-color: #32222d; padding: 22px 48px 18px; border-bottom: 3px solid #b08d57; }
    .brand { font-family: "DejaVu Serif", serif; font-size: 22px; letter-spacing: 1px; color: #f7f3ee; }
    .brand-sub { font-size: 8px; letter-spacing: 2.5px; text-transform: uppercase; color: #b08d57; margin-top: 4px; }
    .content { padding: 30px 48px 60px; }
    .kicker { font-size: 8.5px; letter-spacing: 2.5px; text-transform: uppercase; color: #8d6c3a; }
    h1 { font-family: "DejaVu Serif", serif; font-size: 22px; font-weight: normal; color: #32222d; margin: 6px 0 8px; }
    .subtitle { font-size: 11px; color: #6f635e; margin-bottom: 4px; }
    .meta { font-size: 9px; color: #6f635e; margin-bottom: 22px; padding-bottom: 14px; border-bottom: 1px solid #e3dad1; }
    table { width: 100%; border-collapse: collapse; }
    th { background-color: #efe8e0; color: #32222d; font-size: 8px; letter-spacing: 1.2px; text-transform: uppercase;
         text-align: left; padding: 9px 10px; border-bottom: 1px solid #bdb0a5; }
    td { padding: 9px 10px; border-bottom: 1px solid #e3dad1; vertical-align: top; }
    tr.alt td { background-color: #faf7f3; }
    .num { text-align: right; width: 90px; }
    td.empty { text-align: center; color: #6f635e; padding: 22px 10px; }
    .st-planned { color: #8d6c3a; }
    .st-done { color: #2f5d3a; }
    .st-cancelled { color: #8f2d2d; }
';

$html = '<html><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>';
$html .= '<div class="band"><div class="brand">Turka</div><div class="brand-sub">Салон красоты</div></div>';
$html .= '<div class="content">';
$html .= '<div class="kicker">Отчёт</div>';
$html .= '<h1>' . $e($reportTitles[$type]) . '</h1>';
if ($subtitle !== '') {
    $html .= '<div class="subtitle">' . $e($subtitle) . '</div>';
}
$html .= '<div class="meta">Сформирован: ' . date('d.m.Y H:i') . ' &nbsp;&middot;&nbsp; Строк: ' . count($rows) . '</div>';

$html .= '<table><thead><tr>';
foreach ($columns as $index => $column) {
    $class = ($numericLast && $index === count($columns) - 1) ? ' class="num"' : '';
    $html .= '<th' . $class . '>' . $e($column) . '</th>';
}
$html .= '</tr></thead><tbody>';

if (!$rows) {
    $html .= '<tr><td class="empty" colspan="' . count($columns) . '">Данные не найдены.</td></tr>';
}
foreach ($rows as $rowIndex => $row) {
    $html .= '<tr' . ($rowIndex % 2 === 1 ? ' class="alt"' : '') . '>';
    $lastIndex = count($row) - 1;
    foreach ($row as $cellIndex => $cell) {
        $cell = (string)$cell;
        $classes = [];
        if ($numericLast && $cellIndex === $lastIndex) {
            $classes[] = 'num';
        }
        if (isset($statusClasses[$cell])) {
            $classes[] = $statusClasses[$cell];
        }
        $class = $classes ? ' class="' . implode(' ', $classes) . '"' : '';
        $html .= '<td' . $class . '>' . $e($cell) . '</td>';
    }
    $html .= '</tr>';
}
$html .= '</tbody></table></div></body></html>';

// Шрифт DejaVu Sans нужен, потому что он поддерживает русские буквы.
$options = new \Dompdf\Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', false);

$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Колонтитул с номером страницы рисуем после render(), когда уже известно общее число страниц.
$canvas = $dompdf->getCanvas();
$footerFont = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
$canvas->page_text(36, $canvas->get_height() - 32, 'Салон красоты Turka', $footerFont, 8, [0.44, 0.39, 0.37]);
$canvas->page_text($canvas->get_width() - 112, $canvas->get_height() - 32, 'Страница {PAGE_NUM} из {PAGE_COUNT}', $footerFont, 8, [0.44, 0.39, 0.37]);

$dompdf->stream('report_' . $type . '.pdf', ['Attachment' => true]);
