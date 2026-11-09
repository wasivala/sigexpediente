<?php
/**
 * Exportação CSV dos relatórios (colectivo e individual).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/relatorio.php';
require_can('exportar_relatorios');

$tipo = get('tipo', 'coletivo');
if (!in_array($tipo, ['coletivo', 'individual'], true)) {
    $tipo = 'coletivo';
}

$nomeBase = 'relatorio-' . $tipo . '-' . date('Ymd-His');
$nomeFicheiro = $nomeBase . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nomeFicheiro . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$out = fopen('php://output', 'wb');
if ($out === false) {
    exit;
}

// BOM para compatibilidade com o Microsoft Excel
fwrite($out, "\xEF\xBB\xBF");

if ($tipo === 'individual') {
    $id = get_int('id');
    $stmt = db()->prepare(
        'SELECT e.*, u.nume AS registado_por
         FROM expedientes e
         LEFT JOIN usuarios u ON u.idusuario = e.idusuario
         WHERE e.idexpediente = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $exp = $stmt->fetch();

    if (!$exp) {
        fputcsv($out, ['Expediente não encontrado.'], ';');
        fclose($out);
        exit;
    }

    $stmt = db()->prepare('SELECT * FROM requeridos WHERE idexpediente = :id ORDER BY nome');
    $stmt->execute([':id' => $id]);
    $requeridos = $stmt->fetchAll();

    $stmt = db()->prepare('SELECT * FROM beneficiarios WHERE idexpediente = :id ORDER BY nome');
    $stmt->execute([':id' => $id]);
    $beneficiarios = $stmt->fetchAll();

    fputcsv($out, ['RELATÓRIO INDIVIDUAL DE EXPEDIENTE'], ';');
    fputcsv($out, ['Referência', $exp['referencia']], ';');
    fputcsv($out, ['Nº do processo', processo_rotulo($exp['nrm_processo'] ?? null)], ';');
    fputcsv($out, ['Data de entrada', data_hora_pt((string) $exp['data_entrada'])], ';');
    fputcsv($out, ['Proveniência', $exp['proveniencia']], ';');
    fputcsv($out, ['Assunto', $exp['Assunto']], ';');
    fputcsv($out, ['Descrição', $exp['descricao']], ';');
    fputcsv($out, ['Estado', STATUS_EXPEDIENTE[$exp['status']] ?? $exp['status']], ';');
    fputcsv($out, ['Registado por', $exp['registado_por'] ?? $exp['usuario']], ';');
    fputcsv($out, [], ';');

    fputcsv($out, ['REQUERIDOS'], ';');
    fputcsv($out, ['N/O', 'Nome', 'Patente', 'Ramo', 'Unidade', 'Função', 'Contacto', 'NUIT', 'Banco', 'Conta'], ';');
    foreach ($requeridos as $i => $r) {
        fputcsv($out, [
            $i + 1, $r['nome'], $r['patente'], $r['ramo'], $r['unidade'], $r['funcao'],
            $r['contacto'], $r['nuit'], $r['banco'], $r['conta'],
        ], ';');
    }
    if (!$requeridos) { fputcsv($out, ['—', 'Nenhum requerido associado'], ';'); }

    fputcsv($out, [], ';');
    fputcsv($out, ['BENEFICIÁRIOS'], ';');
    fputcsv($out, ['N/O', 'Nome', 'Contacto', 'Endereço', 'Banco', 'Conta', 'Quantidade'], ';');
    foreach ($beneficiarios as $i => $b) {
        fputcsv($out, [
            $i + 1, $b['nome'], $b['contacto'], $b['endereco'], $b['banco'], $b['conta'], $b['qtd_beneficiario'],
        ], ';');
    }
    if (!$beneficiarios) { fputcsv($out, ['—', 'Nenhum beneficiário associado'], ';'); }

    log_action('Exportação CSV: relatório individual ' . $exp['referencia'], ['idexpediente' => $id]);
} else {
    $f = relatorio_filtros();
    $linhas = relatorio_coletivo_dados($f);

    fputcsv($out, ['RELATÓRIO COLECTIVO DE EXPEDIENTES'], ';');
    fputcsv($out, ['Filtros', relatorio_resumo($f)], ';');
    fputcsv($out, ['Emitido em', date('d/m/Y H:i')], ';');
    fputcsv($out, [], ';');
    fputcsv($out, [
        'N/O', 'Referência', 'Requerente', 'Requerido', 'Assuntos',
        'Contacto de Beneficiário', 'Proveniência', 'Data de Entrada', 'Estado', 'NUIT',
    ], ';');

    foreach ($linhas as $i => $l) {
        $requerente = trim((string) ($l['patente'] ?? '') . ' ' . (string) ($l['requerido_nome'] ?? ''));
        if ($requerente === '') { $requerente = (string) ($l['beneficiario_nome'] ?? ''); }

        $requerido = (string) ($l['unidade'] ?? '');
        if ($requerido === '') { $requerido = (string) ($l['ramo'] ?? ''); }

        $contacto = (string) ($l['beneficiario_contacto'] ?? '');
        if ($contacto === '') { $contacto = (string) ($l['requerido_contacto'] ?? ''); }

        fputcsv($out, [
            $i + 1,
            $l['referencia'],
            $requerente !== '' ? $requerente : '—',
            $requerido !== '' ? $requerido : '—',
            $l['Assunto'],
            $contacto !== '' ? $contacto : '—',
            $l['proveniencia'],
            data_pt((string) $l['data_entrada']),
            STATUS_EXPEDIENTE[$l['status']] ?? $l['status'],
            (string) ($l['nuit'] ?? '—'),
        ], ';');
    }

    if (!$linhas) {
        fputcsv($out, ['—', 'Nenhum registo corresponde aos filtros seleccionados'], ';');
    }

    log_action('Exportação CSV: relatório colectivo (' . count($linhas) . ' registo(s))');
}

fclose($out);
exit;
