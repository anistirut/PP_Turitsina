<?php $activePage = $activePage ?? ''; ?>
<header class="site-header">
    <div class="site-header-inner">
        <span class="site-title">Салон красоты</span>
        <?php if (isLoggedIn()): ?>
            <nav class="nav">
                <a href="clients.php" class="<?= $activePage === 'clients' ? 'active' : '' ?>">Клиенты</a>
                <a href="masters.php" class="<?= $activePage === 'masters' ? 'active' : '' ?>">Мастера</a>
                <a href="services.php" class="<?= $activePage === 'services' ? 'active' : '' ?>">Услуги</a>
                <a href="rooms.php" class="<?= $activePage === 'rooms' ? 'active' : '' ?>">Кабинеты</a>
                <a href="appointments.php" class="<?= $activePage === 'appointments' ? 'active' : '' ?>">Записи</a>
                <a href="reports.php" class="<?= $activePage === 'reports' ? 'active' : '' ?>">Отчёты</a>
                <a href="logout.php">Выход</a>
            </nav>
        <?php endif; ?>
    </div>
</header>
