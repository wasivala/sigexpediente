<?php
/**
 * Envio de e-mail: SMTP (com TLS/SSL e autenticação) ou mail() nativo.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function email_template(string $titulo, string $corpoHtml, string $rodape = ''): string
{
    $app = e(config_get('app_nome', 'SIGExpediente'));
    $rodape = $rodape !== '' ? $rodape : 'Se não solicitou esta acção, ignore esta mensagem.';
    return '<!doctype html><html lang="pt"><head><meta charset="utf-8"></head>'
        . '<body style="margin:0;padding:0;background:#f1f5f9;font-family:Segoe UI,Arial,sans-serif;color:#0f172a;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:30px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0;">'
        . '<tr><td style="background:#14532d;padding:22px 28px;">'
        . '<div style="color:#fff;font-size:19px;font-weight:700;">' . $app . '</div>'
        . '<div style="color:rgba(255,255,255,.6);font-size:12px;">Gestão de Entrada e Saída de Expedientes</div>'
        . '</td></tr>'
        . '<tr><td style="padding:28px;">'
        . '<h1 style="margin:0 0 14px;font-size:20px;color:#14532d;">' . e($titulo) . '</h1>'
        . '<div style="font-size:14px;line-height:1.6;color:#334155;">' . $corpoHtml . '</div>'
        . '</td></tr>'
        . '<tr><td style="padding:16px 28px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:11px;color:#64748b;">'
        . e($rodape)
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/**
 * Envia um e-mail. Devolve true em caso de sucesso.
 * @param string $para       Destinatário
 * @param string $assunto    Assunto
 * @param string $corpoHtml  Corpo em HTML
 */
function enviar_email(string $para, string $assunto, string $corpoHtml): bool
{
    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $host   = trim((string) config_get('smtp_host', ''));
    $from   = trim((string) config_get('mail_from', 'nao-responder@localhost'));
    $fromNm = trim((string) config_get('mail_from_nome', config_get('app_nome', 'SIGExpediente')));

    if ($host === '') {
        // Fallback para mail() nativo
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . sprintf('%s <%s>', $fromNm, $from),
        ];
        $ok = @mail($para, '=?UTF-8?B?' . base64_encode($assunto) . '?=', $corpoHtml, implode("\r\n", $headers));
        if (!$ok) {
            error_log('mail() não conseguiu enviar para ' . $para . ' (nenhum SMTP configurado).');
        }
        return $ok;
    }

    $porta   = (int) config_get('smtp_port', '587');
    $user    = (string) config_get('smtp_user', '');
    $pass    = (string) config_get('smtp_pass', '');
    $secure  = strtolower((string) config_get('smtp_secure', 'tls')); // tls | ssl | none

    $prefixo = ($secure === 'ssl') ? 'ssl://' : '';
    $timeout = 12;

    $contexto = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true],
    ]);

    $errno = 0;
    $errstr = '';
    $sock = @stream_socket_client($prefixo . $host . ':' . $porta, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $contexto);
    if (!$sock) {
        error_log("SMTP: falha ao ligar a {$host}:{$porta} - {$errstr} ({$errno})");
        return false;
    }
    stream_set_timeout($sock, $timeout);

    $ler = function () use ($sock): string {
        $dados = '';
        while (($linha = fgets($sock, 515)) !== false) {
            $dados .= $linha;
            if (isset($linha[3]) && $linha[3] === ' ') {
                break;
            }
        }
        return $dados;
    };
    $cmd = function (string $comando, array $esperados) use ($sock, $ler): bool {
        if ($comando !== '') {
            fwrite($sock, $comando . "\r\n");
        }
        $resp = $ler();
        $codigo = (int) substr($resp, 0, 3);
        if (!in_array($codigo, $esperados, true)) {
            error_log('SMTP: resposta inesperada [' . trim($resp) . '] ao comando ' . substr($comando, 0, 20));
            return false;
        }
        return true;
    };

    $ok = $cmd('', [220])
        && $cmd('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);

    if ($ok && $secure === 'tls') {
        if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            error_log('SMTP: não foi possível iniciar TLS.');
            $ok = false;
        } else {
            $ok = $cmd('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);
        }
    }

    if ($ok && $user !== '') {
        $ok = $cmd('AUTH LOGIN', [334])
            && $cmd(base64_encode($user), [334])
            && $cmd(base64_encode($pass), [235]);
    }

    if ($ok) {
        $ok = $cmd('MAIL FROM:<' . $from . '>', [250])
            && $cmd('RCPT TO:<' . $para . '>', [250, 251])
            && $cmd('DATA', [354]);
    }

    if ($ok) {
        $cabecalhos = [
            'From: ' . sprintf('=?UTF-8?B?%s?= <%s>', base64_encode($fromNm), $from),
            'To: <' . $para . '>',
            'Subject: =?UTF-8?B?' . base64_encode($assunto) . '?=',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . ($_SERVER['SERVER_NAME'] ?? 'localhost') . '>',
        ];
        $corpo = chunk_split(base64_encode($corpoHtml), 76, "\r\n");
        fwrite($sock, implode("\r\n", $cabecalhos) . "\r\n\r\n" . $corpo . "\r\n.\r\n");
        $resp = $ler();
        $ok = (int) substr($resp, 0, 3) === 250;
        if (!$ok) {
            error_log('SMTP: falha ao enviar mensagem [' . trim($resp) . ']');
        }
    }

    @$cmd('QUIT', [221, 250]);
    @fclose($sock);
    return $ok;
}
