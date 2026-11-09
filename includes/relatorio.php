<?php
/**
 * Elementos partilhados pelos relatórios (cabeçalho institucional,
 * filtros, consultas e blocos de assinatura).
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/**
 * Lê e valida os filtros de relatório a partir da query string.
 */
function relatorio_filtros(): array
{
    $dataIni = get('data_inicio');
    $dataFim = get('data_fim');
    $status  = get('status');
    $nuit    = get('nuit');
    $q       = get('q');

    if ($dataIni !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataIni)) { $dataIni = ''; }
    if ($dataFim !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) { $dataFim = ''; }

    if ($dataIni !== '' && $dataFim !== '' && $dataIni > $dataFim) {
        flash_warning('A data inicial não pode ser posterior à data final. Os filtros de data foram ignorados.');
        $dataIni = $dataFim = '';
    }

    if (!array_key_exists($status, STATUS_EXPEDIENTE)) {
        $status = '';
    }

    if ($nuit !== '') {
        if (!preg_match('/^\d{9}$/', $nuit)) {
            flash_warning('O NUIT deve ter exactamente 9 dígitos. O filtro foi ignorado.');
            $nuit = '';
        }
    }

    return [
        'data_inicio' => $dataIni,
        'data_fim'    => $dataFim,
        'status'      => $status,
        'nuit'        => $nuit,
        'q'           => $q,
    ];
}

/**
 * Constrói a cláusula WHERE e os parâmetros para a consulta de relatórios.
 *
 * @return array{0:string,1:array<string,string>}
 */
function relatorio_condicoes(array $f): array
{
    $where = [];
    $params = [];

    if (($f['status'] ?? '') !== '') {
        $where[] = 'e.status = :status';
        $params[':status'] = $f['status'];
    }
    if (($f['data_inicio'] ?? '') !== '') {
        $where[] = 'DATE(e.data_entrada) >= :data_ini';
        $params[':data_ini'] = $f['data_inicio'];
    }
    if (($f['data_fim'] ?? '') !== '') {
        $where[] = 'DATE(e.data_entrada) <= :data_fim';
        $params[':data_fim'] = $f['data_fim'];
    }
    if (($f['nuit'] ?? '') !== '') {
        $where[] = 'r.nuit = :nuit';
        $params[':nuit'] = $f['nuit'];
    }
    if (($f['q'] ?? '') !== '') {
        $where[] = '(e.referencia LIKE :q1 OR e.nrm_processo LIKE :q2 OR e.Assunto LIKE :q3'
            . ' OR e.descricao LIKE :q4 OR e.proveniencia LIKE :q5 OR r.nome LIKE :q6)';
        for ($i = 1; $i <= 6; $i++) {
            $params[':q' . $i] = '%' . $f['q'] . '%';
        }
    }

    return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
}

/** Devolve o número de linhas do relatório colectivo. */
function relatorio_coletivo_total(array $f): int
{
    [$whereSql, $params] = relatorio_condicoes($f);
    $stmt = db()->prepare(
        "SELECT COUNT(*)
         FROM expedientes e
         LEFT JOIN requeridos r ON r.idexpediente = e.idexpediente
         {$whereSql}"
    );
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/**
 * Devolve as linhas do relatório colectivo.
 * Cada linha representa um par expediente/requerido.
 */
function relatorio_coletivo_dados(array $f, int $limite = 0, int $offset = 0): array
{
    [$whereSql, $params] = relatorio_condicoes($f);

    $sql = "SELECT e.idexpediente, e.referencia, e.nrm_processo, e.Assunto, e.proveniencia,
                   e.data_entrada, e.status,
                   r.idrequerido, r.nome AS requerido_nome, r.patente, r.ramo, r.unidade,
                   r.contacto AS requerido_contacto, r.nuit,
                   (SELECT b.nome FROM beneficiarios b
                     WHERE b.idexpediente = e.idexpediente
                     ORDER BY b.idbeneficiario LIMIT 1) AS beneficiario_nome,
                   (SELECT b.contacto FROM beneficiarios b
                     WHERE b.idexpediente = e.idexpediente
                     ORDER BY b.idbeneficiario LIMIT 1) AS beneficiario_contacto
            FROM expedientes e
            LEFT JOIN requeridos r ON r.idexpediente = e.idexpediente
            {$whereSql}
            ORDER BY e.data_entrada ASC, e.idexpediente ASC, r.idrequerido ASC";

    if ($limite > 0) {
        $sql .= ' LIMIT ' . max(1, $limite) . ' OFFSET ' . max(0, $offset);
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Cabeçalho institucional conforme o modelo de relatório em anexo. */
function relatorio_cabecalho(string $assunto, string $dataIni = '', string $dataFim = ''): string
{
    $linhas = '';
    for ($i = 1; $i <= 5; $i++) {
        $valor = trim((string) config_get('inst_linha' . $i, ''));
        if ($valor !== '') {
            $linhas .= '<div class="r-line">' . e($valor) . '</div>';
        }
    }

    $html = '<div class="report-header">' . $linhas . '<div class="r-rule"></div></div>';
    $html .= '<div class="report-meta">'
        . '<div><strong>ASSUNTOS:</strong> ' . e($assunto) . '</div>'
        . '<div><strong>Data inicial:</strong> ' . e($dataIni !== '' ? data_pt($dataIni) : '—') . '</div>'
        . '<div><strong>Data final:</strong> ' . e($dataFim !== '' ? data_pt($dataFim) : '—') . '</div>'
        . '</div>';

    return $html;
}

/** Bloco de assinaturas do modelo (duas entidades subscritoras). */
function relatorio_assinaturas(): string
{
    $assinaturas = [
        [
            'role' => (string) config_get('relatorio_assinante1', 'O Director'),
            'nome' => (string) config_get('relatorio_nome1', ''),
            'posto' => (string) config_get('relatorio_posto1', ''),
        ],
        [
            'role' => (string) config_get('relatorio_assinante2', 'O Chefe da Repartição'),
            'nome' => (string) config_get('relatorio_nome2', ''),
            'posto' => (string) config_get('relatorio_posto2', ''),
        ],
    ];

    $html = '<div class="report-sign">';
    foreach ($assinaturas as $a) {
        $html .= '<div class="sig">'
            . '<div class="role">' . e($a['role']) . '</div>'
            . '<div style="height:34px"></div>'
            . '<div class="line"></div>'
            . '<div class="name">' . e($a['nome'] !== '' ? $a['nome'] : '_______________________') . '</div>';
        if ($a['posto'] !== '') {
            $html .= '<div class="post">(' . e($a['posto']) . ')</div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';

    return $html;
}

/** Resumo legível dos filtros activos. */
function relatorio_resumo(array $f): string
{
    $partes = [];
    if (($f['data_inicio'] ?? '') !== '' || ($f['data_fim'] ?? '') !== '') {
        $partes[] = 'Período: ' . (($f['data_inicio'] ?? '') !== '' ? data_pt($f['data_inicio']) : 'início')
            . ' a ' . (($f['data_fim'] ?? '') !== '' ? data_pt($f['data_fim']) : 'hoje');
    }
    if (($f['status'] ?? '') !== '') {
        $partes[] = 'Estado: ' . STATUS_EXPEDIENTE[$f['status']];
    }
    if (($f['nuit'] ?? '') !== '') {
        $partes[] = 'NUIT: ' . $f['nuit'];
    }
    if (($f['q'] ?? '') !== '') {
        $partes[] = 'Pesquisa: ' . $f['q'];
    }
    return $partes ? implode(' · ', $partes) : 'Sem filtros aplicados';
}
