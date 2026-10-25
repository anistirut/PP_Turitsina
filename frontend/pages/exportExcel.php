<?php

// Выгрузка данных в Excel. В параметре entity указано, что выгружать:
// clients, masters, services, rooms или appointments.

// Подключаем сессию, БД и нужные классы. Гостей requireAuth() отправляет на вход.
session_start();

require_once __DIR__ . '/../../backend/functions/connectionDB.php';
require_once __DIR__ . '/../../backend/functions/session.php';
require_once __DIR__ . '/../../backend/classes/ClientContext.php';
require_once __DIR__ . '/../../backend/classes/MasterContext.php';
require_once __DIR__ . '/../../backend/classes/ServiceContext.php';
require_once __DIR__ . '/../../backend/classes/RoomContext.php';
require_once __DIR__ . '/../../backend/classes/AppointmentContext.php';
require_once __DIR__ . '/../../vendor/autoload.php';

requireAuth();

$entity = $_GET['entity'] ?? '';

// Книга и лист создаются один раз, а в каждой ветке ниже заполняются своя шапка и данные.
$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$filename = 'export.xlsx';

if ($entity === 'clients') {
    $filename = 'clients.xlsx';
    $sheet->setTitle('Клиенты');
    $sheet->fromArray(['Фамилия', 'Имя', 'Телефон', 'Дата рождения'], null, 'A1');

    $clientContext = new ClientContext($mysqli);
    $rowNum = 2;
    foreach ($clientContext->getAll() as $client) {
        $sheet->fromArray([$client->last_name, $client->first_name, $client->phone, $client->birth_date ?? ''], null, "A{$rowNum}");
        $rowNum++;
    }
} elseif ($entity === 'masters') {
    $filename = 'masters.xlsx';
    $sheet->setTitle('Мастера');
    $sheet->fromArray(['Фамилия', 'Имя', 'Телефон', 'Дата приёма', 'Услуги'], null, 'A1');

    $masterContext = new MasterContext($mysqli);
    $rowNum = 2;
    foreach ($masterContext->getAllWithServices() as $row) {
        $master = $row['master'];
        $sheet->fromArray([$master->last_name, $master->first_name, $master->phone, $master->hire_date, $row['services']], null, "A{$rowNum}");
        $rowNum++;
    }
} elseif ($entity === 'services') {
    $filename = 'services.xlsx';
    $sheet->setTitle('Услуги');
    $sheet->fromArray(['Название', 'Длительность, мин', 'Стоимость, ₽'], null, 'A1');

    $serviceContext = new ServiceContext($mysqli);
    $rowNum = 2;
    foreach ($serviceContext->getAll() as $service) {
        $sheet->fromArray([$service->name, $service->duration_minutes, $service->price], null, "A{$rowNum}");
        $rowNum++;
    }
} elseif ($entity === 'rooms') {
    $filename = 'rooms.xlsx';
    $sheet->setTitle('Кабинеты');
    $sheet->fromArray(['Номер', 'Описание'], null, 'A1');

    $roomContext = new RoomContext($mysqli);
    $rowNum = 2;
    foreach ($roomContext->getAll() as $room) {
        $sheet->fromArray([$room->number, $room->description ?? ''], null, "A{$rowNum}");
        $rowNum++;
    }
} elseif ($entity === 'appointments') {
    $filename = 'appointments.xlsx';
    $sheet->setTitle('Записи');
    $sheet->fromArray(['Дата и время', 'Клиент', 'Мастер', 'Услуга', 'Кабинет', 'Статус', 'Комментарий'], null, 'A1');

    $appointmentContext = new AppointmentContext($mysqli);
    $filterClientId = (int)($_GET['filter_client_id'] ?? 0);
    $filterMasterId = (int)($_GET['filter_master_id'] ?? 0);
    $filterServiceId = (int)($_GET['filter_service_id'] ?? 0);

    $rowNum = 2;
    foreach ($appointmentContext->getAllWithDetails($filterClientId, $filterMasterId, $filterServiceId) as $row) {
        $sheet->fromArray([
            $row['appointment_datetime'],
            $row['client_last_name'] . ' ' . $row['client_first_name'],
            $row['master_last_name'] . ' ' . $row['master_first_name'],
            $row['service_name'],
            $row['room_number'],
            $row['status'],
            $row['comment'] ?? '',
        ], null, "A{$rowNum}");
        $rowNum++;
    }
} else {
    http_response_code(400);
    exit('Неизвестный раздел для экспорта.');
}

// Ширину столбцов подгоняем под содержимое, первую строку выделяем жирным.
foreach (range('A', $sheet->getHighestColumn()) as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}
$sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);

// Отдаём файл прямо в браузер на скачивание, на сервере он не сохраняется.
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
