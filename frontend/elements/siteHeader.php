<?php $activePage = $activePage ?? ''; ?>
<header class="site-header">
    <div class="site-header-inner">
        <a class="site-brand" href="clients.php">
            <span class="site-brand-name">Turka</span>
            <span class="site-brand-sub">Салон красоты</span>
        </a>
        <?php if (isLoggedIn()): ?>
            <nav class="nav">
                <a href="clients.php" class="<?= $activePage === 'clients' ? 'active' : '' ?>">Клиенты</a>
                <a href="masters.php" class="<?= $activePage === 'masters' ? 'active' : '' ?>">Мастера</a>
                <a href="services.php" class="<?= $activePage === 'services' ? 'active' : '' ?>">Услуги</a>
                <a href="rooms.php" class="<?= $activePage === 'rooms' ? 'active' : '' ?>">Кабинеты</a>
                <a href="appointments.php" class="<?= $activePage === 'appointments' ? 'active' : '' ?>">Записи</a>
                <a href="reports.php" class="<?= $activePage === 'reports' ? 'active' : '' ?>">Отчёты</a>
                <a href="profile.php" class="<?= $activePage === 'profile' ? 'active' : '' ?>">Профиль</a>
                <a href="logout.php" class="nav-logout">Выход</a>
            </nav>
        <?php endif; ?>
    </div>
</header>
