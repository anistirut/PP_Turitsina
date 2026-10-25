<?php // Номера страниц под таблицей. Ждёт переменные $page и $totalPages; ссылки сохраняют поиск и сортировку. ?>
<?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a class="btn btn-secondary" href="<?= h(queryStringWith(['page' => $page - 1])) ?>">&laquo; Назад</a>
        <?php endif; ?>

        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a class="page-link <?= $p === $page ? 'page-current' : '' ?>" href="<?= h(queryStringWith(['page' => $p])) ?>"><?= $p ?></a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a class="btn btn-secondary" href="<?= h(queryStringWith(['page' => $page + 1])) ?>">Вперёд &raquo;</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
