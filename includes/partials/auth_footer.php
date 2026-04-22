<?php
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config.php';
}
?>
        </div>
    </main>

    <footer class="zz-auth__legal">
        &copy; <?= date('Y') ?> ZenZone &middot; Capstone Project
    </footer>

    <script src="<?= htmlspecialchars(BASE_URL . '/assets/js/zenzone.js', ENT_QUOTES, 'UTF-8') ?>" defer></script>
</body>
</html>
