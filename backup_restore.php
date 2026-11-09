<?php
/**
 * Restauro de uma cópia de segurança (POST).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/backup.php';
require_can('gerir_backup');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/backup.php');
}
verify_csrf();

$criarAntes = isset($_POST['criar_antes']) && $_POST['criar_antes'] === '1';
$ficheiro = post('ficheiro');
$caminho = null;

if ($ficheiro !== '') {
    $caminho = backup_caminho($ficheiro);
    if ($caminho === null) {
        flash_error('A cópia de segurança seleccionada não foi encontrada.');
        redirect('/backup.php');
    }
} else {
    $upload = $_FILES['backup_file'] ?? null;
    if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        flash_error('Selecione um ficheiro de backup para restaurar.');
        redirect('/backup.php');
    }
    if ($upload['error'] !== UPLOAD_ERR_OK) {
        flash_error('Falha no carregamento do ficheiro (código ' . (int) $upload['error'] . ').');
        redirect('/backup.php');
    }
    if ((int) $upload['size'] > 50 * 1024 * 1024) {
        flash_error('O ficheiro de backup não pode exceder 50 MB.');
        redirect('/backup.php');
    }
    $ext = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));
    if ($ext !== 'sql') {
        flash_error('Formato inválido. Carregue um ficheiro .sql gerado pelo sistema.');
        redirect('/backup.php');
    }

    $tmp = (string) $upload['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        flash_error('Ficheiro de backup inválido.');
        redirect('/backup.php');
    }

    $destino = backup_dir() . DIRECTORY_SEPARATOR . 'upload_' . date('Ymd_His') . '.sql';
    if (!@move_uploaded_file($tmp, $destino)) {
        flash_error('Não foi possível guardar temporariamente o ficheiro carregado.');
        redirect('/backup.php');
    }
    $caminho = $destino;
}

$copiaSeguranca = null;
try {
    if ($criarAntes) {
        $copiaSeguranca = backup_criar();
    }

    $executadas = backup_restaurar($caminho);
    log_action('Backup restaurado: ' . basename((string) $caminho) . ' (' . $executadas . ' instruções)');

    $mensagem = 'Base de dados restaurada com sucesso (' . numero_pt($executadas) . ' instruções executadas).';
    if ($copiaSeguranca !== null) {
        $mensagem .= ' Cópia de segurança prévia: ' . $copiaSeguranca['nome'] . '.';
    }
    flash_success($mensagem);
} catch (Throwable $e) {
    error_log('Erro ao restaurar backup: ' . $e->getMessage());
    flash_error('Não foi possível restaurar a base de dados: ' . $e->getMessage());
} finally {
    if (str_starts_with(basename((string) $caminho), 'upload_')) {
        @unlink((string) $caminho);
    }
}

redirect('/backup.php');
