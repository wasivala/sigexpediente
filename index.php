<?php
/**
 * Ponto de entrada da aplicação.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

redirect(auth_check() ? '/dashboard.php' : '/login.php');
