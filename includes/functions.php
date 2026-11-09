<?php
/**
 * Funções utilitárias partilhadas por toda a aplicação.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/* =====================================================================
 * Saída / URLs
 * ===================================================================== */

/** Escapa texto para HTML. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Constrói um URL interno absoluto (respeitando a subpasta do projecto). */
function url(string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    return BASE_URL . ($path === '/' ? '/' : $path);
}

/** Redirecciona e termina. */
function redirect(string $path): void
{
    header('Location: ' . (preg_match('#^https?://#i', $path) ? $path : url($path)));
    exit;
}

/** Caminho actualmente pedido (para marcar o menu activo). */
function current_path(): string
{
    return basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
}

/** Constrói um URL absoluto (para links em e-mails). */
function absolute_url(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . url($path);
}

/* =====================================================================
 * Mensagens flash
 * ===================================================================== */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_success(string $m): void { flash('success', $m); }
function flash_error(string $m): void { flash('danger', $m); }
function flash_info(string $m): void { flash('info', $m); }
function flash_warning(string $m): void { flash('warning', $m); }

function get_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

/* =====================================================================
 * CSRF
 * ===================================================================== */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Sessão expirada ou pedido inválido. Recarregue a página e tente novamente.');
    }
}

/* =====================================================================
 * Configurações persistentes (tabela configuracoes)
 * ===================================================================== */

function app_settings(bool $reload = false): array
{
    static $cache = null;
    if ($cache === null || $reload) {
        $cache = [];
        try {
            foreach (db()->query('SELECT chave, valor FROM configuracoes') as $row) {
                $cache[$row['chave']] = $row['valor'];
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return $cache;
}

function config_get(string $chave, ?string $default = null): ?string
{
    $settings = app_settings();
    return array_key_exists($chave, $settings) && $settings[$chave] !== null
        ? (string) $settings[$chave]
        : $default;
}

function config_set(string $chave, ?string $valor): void
{
    $stmt = db()->prepare(
        'INSERT INTO configuracoes (chave, valor) VALUES (:c, :v)
         ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
    );
    $stmt->execute([':c' => $chave, ':v' => $valor]);
    app_settings(true);
}

/* =====================================================================
 * Formatação
 * ===================================================================== */

function data_pt(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    return $ts ? date('d/m/Y', $ts) : '—';
}

function data_hora_pt(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    return $ts ? date('d/m/Y H:i', $ts) : '—';
}

function data_input(?string $datetime, bool $comHora = false): string
{
    if (!$datetime) {
        return '';
    }
    $ts = strtotime($datetime);
    if (!$ts) {
        return '';
    }
    return date($comHora ? 'Y-m-d\TH:i' : 'Y-m-d', $ts);
}

function numero_pt($valor, int $decimais = 0): string
{
    return number_format((float) $valor, $decimais, ',', '.');
}

function status_badge(?string $status): string
{
    $status = strtolower((string) $status);
    $labels = STATUS_EXPEDIENTE;
    $label = $labels[$status] ?? ucfirst($status ?: '—');
    return '<span class="badge badge-' . e($status ?: 'muted') . '">' . e($label) . '</span>';
}

/** Indica se um expediente é interno (sem número de processo). */
function processo_e_interno(?string $nrm_processo): bool
{
    return trim((string) $nrm_processo) === '';
}

/** Rótulo do número de processo, com substituto para expedientes internos. */
function processo_rotulo(?string $nrm_processo): string
{
    $nrm = trim((string) $nrm_processo);
    return $nrm !== '' ? $nrm : 'Expediente interno';
}

function iniciais(string $nome): string
{
    $partes = preg_split('/\s+/u', trim($nome)) ?: [];
    $partes = array_values(array_filter($partes));
    if (!$partes) {
        return '?';
    }
    $ini = mb_substr($partes[0], 0, 1);
    if (count($partes) > 1) {
        $ini .= mb_substr($partes[count($partes) - 1], 0, 1);
    }
    return mb_strtoupper($ini);
}

/* =====================================================================
 * Validação
 * ===================================================================== */

/**
 * Regra de nomes: apenas letras (incluindo acentuadas), espaços,
 * apóstrofo, hífen e ponto. Nunca dígitos.
 */
function validar_nome(string $valor, string $campo, int $max = 100, ?string &$erro = null): bool
{
    $valor = trim($valor);
    if ($valor === '') {
        $erro = "O campo {$campo} é obrigatório.";
        return false;
    }
    if (mb_strlen($valor) > $max) {
        $erro = "O campo {$campo} não pode exceder {$max} caracteres.";
        return false;
    }
    if (preg_match('/\d/u', $valor)) {
        $erro = "O campo {$campo} só pode conter letras (não são permitidos números).";
        return false;
    }
    if (!preg_match("/^[\p{L}][\p{L}\s'’\-\.]*$/u", $valor)) {
        $erro = "O campo {$campo} contém caracteres inválidos. Use apenas letras.";
        return false;
    }
    if (mb_strlen(preg_replace('/\s+/u', '', $valor)) < 2) {
        $erro = "O campo {$campo} deve ter pelo menos 2 letras.";
        return false;
    }
    return true;
}

/** Texto genérico (descrição/assunto): aceita letras, números e pontuação. */
function validar_texto(string $valor, string $campo, int $min = 2, int $max = 255, ?string &$erro = null): bool
{
    $valor = trim($valor);
    if ($valor === '') {
        $erro = "O campo {$campo} é obrigatório.";
        return false;
    }
    if (mb_strlen($valor) < $min) {
        $erro = "O campo {$campo} deve ter pelo menos {$min} caracteres.";
        return false;
    }
    if (mb_strlen($valor) > $max) {
        $erro = "O campo {$campo} não pode exceder {$max} caracteres.";
        return false;
    }
    return true;
}

/** Apenas dígitos, com comprimento variável. */
function validar_digitos(string $valor, string $campo, int $min, int $max, ?string &$erro = null): bool
{
    $valor = trim($valor);
    if ($valor === '') {
        $erro = "O campo {$campo} é obrigatório.";
        return false;
    }
    if (!ctype_digit($valor)) {
        $erro = "O campo {$campo} deve conter apenas números.";
        return false;
    }
    $len = strlen($valor);
    if ($len < $min || $len > $max) {
        $erro = "O campo {$campo} deve ter entre {$min} e {$max} dígitos.";
        return false;
    }
    return true;
}

function validar_email(string $valor, ?string &$erro = null): bool
{
    $valor = trim($valor);
    if ($valor === '' || !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um endereço de e-mail válido.';
        return false;
    }
    if (mb_strlen($valor) > 50) {
        $erro = 'O e-mail não pode exceder 50 caracteres.';
        return false;
    }
    return true;
}

/** Política de palavra-passe: mínimo 8 caracteres, letra e número. */
function validar_senha(string $senha, ?string &$erro = null): bool
{
    if (strlen($senha) < 8) {
        $erro = 'A palavra-passe deve ter pelo menos 8 caracteres.';
        return false;
    }
    if (!preg_match('/[A-Za-zÀ-ÿ]/', $senha)) {
        $erro = 'A palavra-passe deve conter pelo menos uma letra.';
        return false;
    }
    if (!preg_match('/\d/', $senha)) {
        $erro = 'A palavra-passe deve conter pelo menos um número.';
        return false;
    }
    if (strlen($senha) > 72) {
        $erro = 'A palavra-passe não pode exceder 72 caracteres.';
        return false;
    }
    return true;
}

/**
 * Valida datas. Não permite datas anteriores a hoje.
 * Se $ignorar (data já gravada) for indicada e o valor não tiver mudado,
 * a validação é ignorada (edição de registos antigos).
 */
function validar_data_nao_passada(string $valor, string $campo, ?string $ignorar = null, ?string &$erro = null): bool
{
    $valor = trim($valor);
    if ($valor === '') {
        $erro = "O campo {$campo} é obrigatório.";
        return false;
    }
    $ts = strtotime($valor);
    if ($ts === false) {
        $erro = "O campo {$campo} contém uma data inválida.";
        return false;
    }
    if ($ignorar !== null && $ignorar !== '' && date('Y-m-d\TH:i', $ts) === date('Y-m-d\TH:i', strtotime($ignorar))) {
        return true;
    }
    $hoje = strtotime(date('Y-m-d'));
    if ($ts < $hoje) {
        $erro = "O campo {$campo} não pode ser anterior à data de hoje (" . date('d/m/Y') . ').';
        return false;
    }
    return true;
}

function validar_opcao(string $valor, array $opcoes, string $campo, ?string &$erro = null): bool
{
    if (!array_key_exists($valor, $opcoes)) {
        $erro = "Selecione uma opção válida para {$campo}.";
        return false;
    }
    return true;
}

/** Recolhe e limpa um campo de texto do POST. */
function post(string $chave, string $default = ''): string
{
    $v = $_POST[$chave] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function post_int(string $chave, int $default = 0): int
{
    $v = $_POST[$chave] ?? null;
    return is_numeric($v) ? (int) $v : $default;
}

/* =====================================================================
 * Upload de documentos (guardados em LONGBLOB)
 * ===================================================================== */

/**
 * Lê um ficheiro carregado e devolve ['blob','mime','nome'] ou null se vazio.
 * Lança RuntimeException em caso de erro de validação.
 */
function ler_upload(array $file): ?array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no carregamento do documento (código ' . $file['error'] . ').');
    }
    if (($file['size'] ?? 0) > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('O documento excede o tamanho máximo de ' . (MAX_UPLOAD_BYTES / 1024 / 1024) . ' MB.');
    }
    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_UPLOAD_EXT, true)) {
        throw new RuntimeException('Formato não permitido. Use: ' . implode(', ', ALLOWED_UPLOAD_EXT) . '.');
    }
    $conteudo = @file_get_contents($file['tmp_name']);
    if ($conteudo === false) {
        throw new RuntimeException('Não foi possível ler o documento carregado.');
    }
    $mime = 'application/octet-stream';
    if (class_exists('finfo')) {
        $fi = new finfo(FILEINFO_MIME_TYPE);
        $detectado = $fi->buffer($conteudo) ?: '';
        if ($detectado !== '' && !in_array($detectado, ALLOWED_UPLOAD_MIME, true)) {
            throw new RuntimeException('O conteúdo do ficheiro não corresponde a um formato permitido.');
        }
        if ($detectado !== '') {
            $mime = $detectado;
        }
    }
    return ['blob' => $conteudo, 'mime' => $mime, 'nome' => basename((string) $file['name'])];
}

/** Detecta o MIME real de um blob guardado. */
function mime_de_blob(string $blob): string
{
    if ($blob === '') {
        return 'application/octet-stream';
    }
    if (str_starts_with($blob, '%PDF-')) {
        return 'application/pdf';
    }
    if (str_starts_with($blob, "\xFF\xD8\xFF")) {
        return 'image/jpeg';
    }
    if (str_starts_with($blob, "\x89PNG")) {
        return 'image/png';
    }
    if (str_starts_with($blob, 'GIF8')) {
        return 'image/gif';
    }
    if (str_starts_with($blob, 'RIFF') && substr($blob, 8, 4) === 'WEBP') {
        return 'image/webp';
    }
    return 'application/octet-stream';
}

/* =====================================================================
 * Paginação
 * ===================================================================== */

function pagination(int $total, int $porPagina, int $pagina, array $query = []): array
{
    $porPagina = max(1, $porPagina);
    $totalPaginas = max(1, (int) ceil($total / $porPagina));
    $pagina = min(max(1, $pagina), $totalPaginas);
    $offset = ($pagina - 1) * $porPagina;
    unset($query['pagina']);
    return [
        'total' => $total,
        'por_pagina' => $porPagina,
        'pagina' => $pagina,
        'total_paginas' => $totalPaginas,
        'offset' => $offset,
        'inicio' => $total === 0 ? 0 : $offset + 1,
        'fim' => min($offset + $porPagina, $total),
        'query' => $query,
    ];
}

function pagination_links(array $p): string
{
    if ($p['total_paginas'] <= 1) {
        return '';
    }
    $mk = function (int $n, string $label, bool $activo = false, bool $disabled = false) use ($p): string {
        $q = array_merge($p['query'], ['pagina' => $n]);
        $href = '?' . http_build_query($q);
        $cls = 'page-link' . ($activo ? ' is-active' : '') . ($disabled ? ' is-disabled' : '');
        return '<a class="' . $cls . '" href="' . e($href) . '">' . $label . '</a>';
    };

    $html = '<nav class="pagination" aria-label="Paginação">';
    $html .= $mk(max(1, $p['pagina'] - 1), '&laquo;', false, $p['pagina'] <= 1);

    $inicio = max(1, $p['pagina'] - 2);
    $fim = min($p['total_paginas'], $p['pagina'] + 2);
    if ($inicio > 1) {
        $html .= $mk(1, '1');
        if ($inicio > 2) {
            $html .= '<span class="page-dots">…</span>';
        }
    }
    for ($i = $inicio; $i <= $fim; $i++) {
        $html .= $mk($i, (string) $i, $i === $p['pagina']);
    }
    if ($fim < $p['total_paginas']) {
        if ($fim < $p['total_paginas'] - 1) {
            $html .= '<span class="page-dots">…</span>';
        }
        $html .= $mk($p['total_paginas'], (string) $p['total_paginas']);
    }
    $html .= $mk(min($p['total_paginas'], $p['pagina'] + 1), '&raquo;', false, $p['pagina'] >= $p['total_paginas']);
    $html .= '</nav>';
    return $html;
}

/* =====================================================================
 * Auditoria / Histórico
 * ===================================================================== */

/**
 * Regista uma acção no histórico.
 * $ids aceita: idexpediente, idrequerido, idbeneficiario, idsaida, idusuario.
 */
function log_action(string $accao, array $ids = [], ?string $utilizador = null, ?int $idusuario = null): void
{
    $u = $GLOBALS['auth_user'] ?? null;
    $nome = $utilizador ?? ($u['nume'] ?? 'Sistema');
    $uid = $idusuario ?? ($u['idusuario'] ?? null);

    try {
        $stmt = db()->prepare(
            'INSERT INTO logs (usuario, accao, data, idbeneficiario, idexpediente, idrequerido, idsaida, idusuario)
             VALUES (:usuario, :accao, NOW(), :b, :e, :r, :s, :u)'
        );
        $stmt->execute([
            ':usuario' => mb_substr($nome, 0, 50),
            ':accao'   => mb_substr($accao, 0, 100),
            ':b' => $ids['idbeneficiario'] ?? null,
            ':e' => $ids['idexpediente'] ?? null,
            ':r' => $ids['idrequerido'] ?? null,
            ':s' => $ids['idsaida'] ?? null,
            ':u' => $uid,
        ]);
    } catch (Throwable $e) {
        error_log('Falha ao registar log: ' . $e->getMessage());
    }
}

/* =====================================================================
 * Diversos
 * ===================================================================== */

/** Devolve o valor de um campo de filtro GET. */
function get(string $chave, string $default = ''): string
{
    $v = $_GET[$chave] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function get_int(string $chave, int $default = 0): int
{
    $v = $_GET[$chave] ?? null;
    return is_numeric($v) ? (int) $v : $default;
}

/** Verifica se pelo menos um filtro está preenchido. */
function tem_filtros(array $valores): bool
{
    foreach ($valores as $v) {
        if ($v !== '' && $v !== null && $v !== 0) {
            return true;
        }
    }
    return false;
}

/** Gera um nome de ficheiro seguro. */
function slug(string $texto): string
{
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;
    $texto = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $texto) ?? '');
    return trim($texto, '-') ?: 'ficheiro';
}

/** Lê o conteúdo binário de um documento, conforme o tipo. */
function carregar_documento(string $tipo, int $id): ?array
{
    $map = [
        'expediente' => ['expedientes', 'idexpediente', 'scan_documento'],
        'saida'      => ['saida_expedientes', 'idsaida', 'scan_documento'],
    ];
    if (!isset($map[$tipo])) {
        return null;
    }
    [$tabela, $pk, $campo] = $map[$tipo];
    $stmt = db()->prepare("SELECT {$campo} AS `blob` FROM {$tabela} WHERE {$pk} = :id");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row || $row['blob'] === null || $row['blob'] === '') {
        return null;
    }
    return ['blob' => $row['blob'], 'mime' => mime_de_blob($row['blob'])];
}
