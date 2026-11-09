<?php
/**
 * Visualização / descarregamento de documentos digitalizados guardados em LONGBLOB.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_can('ver_documentos');

$tipo = get('tipo');
$id = get_int('id', 0);

$doc = carregar_documento($tipo, $id);
if (!$doc) {
    http_response_code(404);
    exit('Documento não encontrado.');
}

$ext = match ($doc['mime']) {
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
    default => 'bin',
};

$nome = 'documento-' . $tipo . '-' . $id . '.' . $ext;

header('Content-Type: ' . $doc['mime']);
header('Content-Length: ' . strlen($doc['blob']));
header('Content-Disposition: inline; filename="' . $nome . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');
echo $doc['blob'];
exit;
