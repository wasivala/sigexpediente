<?php
/**
 * Geração, listagem e restauro de cópias de segurança da base de dados.
 * Implementação em PHP puro (sem depender de mysqldump).
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/** Garante que o directório de backups existe. */
function backup_dir(): string
{
    if (!is_dir(BACKUP_DIR)) {
        @mkdir(BACKUP_DIR, 0775, true);
    }
    return BACKUP_DIR;
}

/** Lista as cópias de segurança existentes, da mais recente para a mais antiga. */
function backup_listar(): array
{
    $dir = backup_dir();
    $itens = [];
    foreach (glob($dir . '/backup_*.sql') ?: [] as $caminho) {
        if (!is_file($caminho)) {
            continue;
        }
        $itens[] = [
            'nome'    => basename($caminho),
            'tamanho' => (int) filesize($caminho),
            'data'    => (int) filemtime($caminho),
        ];
    }
    usort($itens, static fn(array $a, array $b): int => $b['data'] <=> $a['data']);
    return $itens;
}

/** Valida e devolve o caminho absoluto de um backup existente. */
function backup_caminho(string $nome): ?string
{
    $nome = basename(trim($nome));
    if (!preg_match('/^backup_\d{8}_\d{6}(?:_\d+)?\.sql$/', $nome)) {
        return null;
    }
    $caminho = backup_dir() . DIRECTORY_SEPARATOR . $nome;
    if (!is_file($caminho)) {
        return null;
    }
    $real = realpath($caminho);
    $base = realpath(backup_dir());
    if ($real === false || $base === false || !str_starts_with($real, $base)) {
        return null;
    }
    return $real;
}

/** Formata bytes para leitura humana. */
function backup_tamanho(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    }
    return number_format($bytes / 1024 / 1024, 2, ',', '.') . ' MB';
}

/**
 * Cria uma cópia de segurança completa (estrutura + dados).
 *
 * @return array{nome:string,tamanho:int,tabelas:int,registos:int}
 */
function backup_criar(): array
{
    @set_time_limit(300);
    @ini_set('memory_limit', '512M');

    $pdo = db();
    $tabelas = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);

    $sql = "-- =====================================================================\n"
        . "-- SIGExpediente - Cópia de segurança\n"
        . '-- Base de dados: ' . DB_NAME . "\n"
        . '-- Gerado em: ' . date('Y-m-d H:i:s') . "\n"
        . "-- =====================================================================\n\n"
        . "SET NAMES utf8mb4;\n"
        . "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    $totalRegistos = 0;
    $totalTabelas = 0;

    foreach ($tabelas as $t) {
        $tabela = (string) $t[0];
        if ($tabela === '') {
            continue;
        }
        $ident = '`' . str_replace('`', '``', $tabela) . '`';

        $createStmt = $pdo->query('SHOW CREATE TABLE ' . $ident);
        $createRow = $createStmt->fetch(PDO::FETCH_NUM);
        $create = (string) ($createRow[1] ?? '');

        $sql .= "DROP TABLE IF EXISTS {$ident};\n" . $create . ";\n\n";
        $totalTabelas++;

        $rows = $pdo->query('SELECT * FROM ' . $ident);
        while (($row = $rows->fetch(PDO::FETCH_ASSOC)) !== false) {
            $valores = [];
            foreach ($row as $valor) {
                if ($valor === null) {
                    $valores[] = 'NULL';
                } elseif (is_int($valor) || is_float($valor)) {
                    $valores[] = (string) $valor;
                } elseif (is_string($valor) && $valor !== '' && !preg_match('//u', $valor)) {
                    // Conteúdo binário (documentos digitalizados) em hexadecimal.
                    $valores[] = '0x' . bin2hex($valor);
                } else {
                    $quoted = $pdo->quote((string) $valor);
                    // Evita quebras de linha dentro de instruções SQL.
                    $quoted = str_replace(["\r", "\n", "\0", "\x1a"], ['\\r', '\\n', '\\0', '\\Z'], $quoted);
                    $valores[] = $quoted;
                }
            }
            $sql .= "INSERT INTO {$ident} VALUES (" . implode(', ', $valores) . ");\n";
            $totalRegistos++;
        }
        $sql .= "\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    $dir = backup_dir();
    $base = 'backup_' . date('Ymd_His');
    $caminho = $dir . DIRECTORY_SEPARATOR . $base . '.sql';
    $n = 1;
    while (is_file($caminho)) {
        $caminho = $dir . DIRECTORY_SEPARATOR . $base . '_' . $n . '.sql';
        $n++;
    }

    if (@file_put_contents($caminho, $sql, LOCK_EX) === false) {
        throw new RuntimeException('Não foi possível escrever o ficheiro de backup. Verifique as permissões da pasta storage/backups.');
    }

    return [
        'nome'     => basename($caminho),
        'tamanho'  => (int) filesize($caminho),
        'tabelas'  => $totalTabelas,
        'registos' => $totalRegistos,
    ];
}

/** Elimina uma cópia de segurança. */
function backup_apagar(string $nome): bool
{
    $caminho = backup_caminho($nome);
    if ($caminho === null) {
        return false;
    }
    return @unlink($caminho);
}

/**
 * Restaura a base de dados a partir de um ficheiro .sql.
 * Devolve o número de instruções executadas.
 */
function backup_restaurar(string $caminho): int
{
    @set_time_limit(300);
    @ini_set('memory_limit', '512M');

    if (!is_file($caminho) || !is_readable($caminho)) {
        throw new RuntimeException('Ficheiro de backup não encontrado ou sem permissões de leitura.');
    }

    $conteudo = (string) file_get_contents($caminho);
    if ($conteudo === '') {
        throw new RuntimeException('O ficheiro de backup está vazio.');
    }

    // Remove BOM e linhas de comentário.
    $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo) ?? $conteudo;
    $conteudo = preg_replace('/^[ \t]*--.*$/m', '', $conteudo) ?? $conteudo;

    $partes = preg_split('/;[ \t]*(?:\r?\n|$)/', $conteudo) ?: [];

    $pdo = db();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $executadas = 0;

    try {
        foreach ($partes as $parte) {
            $stmt = trim($parte);
            if ($stmt === '' || $stmt === '0') {
                continue;
            }
            try {
                $pdo->exec($stmt);
                $executadas++;
            } catch (Throwable $e) {
                $trecho = mb_substr(preg_replace('/\s+/', ' ', $stmt) ?? $stmt, 0, 160);
                throw new RuntimeException('Falha ao restaurar (' . $e->getMessage() . '). Instrução: ' . $trecho);
            }
        }
    } finally {
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        } catch (Throwable $e) {
            // ignorar
        }
    }

    return $executadas;
}
