<?php
/**
 * Terminar sessão.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

logout_user();
start_session();
flash_info('Sessão terminada com sucesso.');
redirect('/login.php');
