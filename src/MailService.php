<?php

declare(strict_types=1);

/**
 * Envío de correo por SMTP sin librerías externas.
 *
 * Configuración (primero .env; si MAIL_HOST está vacío se usa la configuración de correo del ERP,
 * tabla `config_system`, igual que el sistema anterior):
 *   MAIL_HOST, MAIL_PORT (25), MAIL_SECURITY (none | tls | ssl), MAIL_USER, MAIL_PASSWORD,
 *   MAIL_FROM, MAIL_FROM_NAME.
 * Las credenciales nunca se guardan en el código ni se escriben en el log.
 */
final class MailService
{
    /** @var array{host:string,port:int,security:string,user:string,password:string,from:string,from_name:string,reply_to:string,logo_html:string}|null */
    private ?array $config = null;

    /**
     * @param int $userId Usuario que dispara el envío: igual que el sistema anterior, si ese usuario tiene su propia
     *                    cuenta de correo en el ERP se usa esa; el remitente muestra su nombre y responde a su correo.
     */
    public function __construct(private ?PDO $erpPdo = null, private int $userId = 0)
    {
    }

    /** @return array{host:string,port:int,security:string,user:string,password:string,from:string,from_name:string,reply_to:string,logo_html:string} */
    private function config(): array
    {
        if ($this->config !== null) {
            return $this->config;
        }
        $env = static fn(string $k): string => trim((string)(Env::get($k, '') ?? ''));
        $cfg = [
            'host' => $env('MAIL_HOST'),
            'port' => (int)($env('MAIL_PORT') ?: 25),
            'security' => strtolower($env('MAIL_SECURITY') ?: 'none'),
            'user' => $env('MAIL_USER'),
            'password' => (string)(Env::get('MAIL_PASSWORD', '') ?? ''),
            'from' => $env('MAIL_FROM'),
            'from_name' => $env('MAIL_FROM_NAME'),
            'reply_to' => '',
            'logo_html' => '',
        ];
        $explicit = $cfg['host'] !== ''; // si .env define MAIL_HOST se usa tal cual
        if (!$explicit && $this->erpPdo !== null) {
            // 1) Cuenta general del ERP (config_system), como en el sistema anterior.
            $row = $this->erpPdo->query('SELECT * FROM config_system LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
            $cfg['host'] = trim((string)($row['conf_mailserver'] ?? ''));
            $cfg['user'] = trim((string)($row['conf_mail_accountname'] ?? ''));
            $cfg['password'] = (string)($row['conf_mail_password'] ?? '');
            if (trim((string)($row['conf_mail_image'] ?? '')) !== '' && trim((string)($row['conf_shopadmin_url'] ?? '')) !== '') {
                $cfg['logo_html'] = "<br><img src='" . htmlspecialchars(trim((string)$row['conf_shopadmin_url']) . 'images/companies/' . trim((string)$row['conf_mail_image']), ENT_QUOTES, 'UTF-8') . "'>";
            }
        }
        if ($this->erpPdo !== null && $this->userId > 0) {
            $st = $this->erpPdo->prepare('SELECT conf_mailserver, conf_mail_accountname, conf_mail_password, user_firstname, user_lastname, user_mail FROM user WHERE id = ?');
            $st->execute([$this->userId]);
            $u = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            // 2) Cuenta propia del usuario (solo si tiene servidor, cuenta y clave), igual que index.php del respaldo.
            if (!$explicit && trim((string)($u['conf_mailserver'] ?? '')) !== '' && trim((string)($u['conf_mail_accountname'] ?? '')) !== '' && (string)($u['conf_mail_password'] ?? '') !== '') {
                $cfg['host'] = trim((string)$u['conf_mailserver']);
                $cfg['user'] = trim((string)$u['conf_mail_accountname']);
                $cfg['password'] = (string)$u['conf_mail_password'];
            }
            if ($cfg['from_name'] === '') {
                $cfg['from_name'] = trim(trim((string)($u['user_firstname'] ?? '')) . ' ' . trim((string)($u['user_lastname'] ?? '')));
            }
            $cfg['reply_to'] = trim((string)($u['user_mail'] ?? ''));
        }
        if ($cfg['from_name'] === '') {
            $cfg['from_name'] = 'Unibag ERP';
        }
        if ($cfg['from'] === '') {
            $cfg['from'] = $cfg['user'];
        }
        if ($cfg['host'] === '' || $cfg['from'] === '') {
            throw new RuntimeException('Correo sin configurar: falta la cuenta de correo en el ERP (configuración del sistema) o MAIL_HOST/MAIL_FROM en .env.');
        }
        return $this->config = $cfg;
    }

    private static function encodeHeader(string $text): string
    {
        return preg_match('/[^\x20-\x7E]/', $text) === 1 ? '=?UTF-8?B?' . base64_encode($text) . '?=' : $text;
    }

    private static function cleanAddress(string $addr): string
    {
        $addr = trim($addr);
        if (!filter_var($addr, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Dirección de correo inválida: ' . $addr);
        }
        return $addr;
    }

    /** @param resource $fp */
    private function read($fp): array
    {
        $code = 0;
        $text = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $text .= $line;
            $code = (int)substr($line, 0, 3);
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        return [$code, trim($text)];
    }

    /** @param resource $fp */
    private function cmd($fp, string $line, array $ok): string
    {
        fwrite($fp, $line . "\r\n");
        [$code, $text] = $this->read($fp);
        if (!in_array($code, $ok, true)) {
            throw new RuntimeException('SMTP: respuesta inesperada (' . $code . ') ' . preg_replace('/\s+/', ' ', $text));
        }
        return $text;
    }

    /**
     * @param string[] $to Direcciones destino.
     * @param string   $html Cuerpo HTML.
     */
    public function send(array $to, string $subject, string $html): void
    {
        $cfg = $this->config();
        $to = array_values(array_unique(array_map([self::class, 'cleanAddress'], $to)));
        if ($to === []) {
            throw new RuntimeException('No hay destinatarios para el correo.');
        }
        $from = self::cleanAddress($cfg['from']);
        $remote = ($cfg['security'] === 'ssl' ? 'ssl://' : 'tcp://') . $cfg['host'] . ':' . $cfg['port'];
        $fp = @stream_socket_client($remote, $errno, $errstr, 12, STREAM_CLIENT_CONNECT);
        if (!$fp) {
            throw new RuntimeException('No se pudo conectar al servidor de correo ' . $cfg['host'] . ':' . $cfg['port'] . ' (' . $errstr . ').');
        }
        stream_set_timeout($fp, 20);
        try {
            [$code] = $this->read($fp);
            if ($code !== 220) {
                throw new RuntimeException('SMTP: el servidor no respondió al conectar (' . $code . ').');
            }
            $host = gethostname() ?: 'unibag';
            $this->cmd($fp, 'EHLO ' . $host, [250]);
            if ($cfg['security'] === 'tls') {
                $this->cmd($fp, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('SMTP: no se pudo iniciar TLS.');
                }
                $this->cmd($fp, 'EHLO ' . $host, [250]);
            }
            if ($cfg['user'] !== '') {
                $this->cmd($fp, 'AUTH LOGIN', [334]);
                $this->cmd($fp, base64_encode($cfg['user']), [334]);
                $this->cmd($fp, base64_encode($cfg['password']), [235]);
            }
            $this->cmd($fp, 'MAIL FROM:<' . $from . '>', [250]);
            foreach ($to as $rcpt) {
                $this->cmd($fp, 'RCPT TO:<' . $rcpt . '>', [250, 251]);
            }
            $this->cmd($fp, 'DATA', [354]);

            $headers = [
                'Date: ' . date('r'),
                'From: ' . self::encodeHeader($cfg['from_name']) . ' <' . $from . '>',
                'To: ' . implode(', ', $to),
                ($cfg['reply_to'] !== '' && filter_var($cfg['reply_to'], FILTER_VALIDATE_EMAIL) ? 'Reply-To: ' . $cfg['reply_to'] : null),
                'Subject: ' . self::encodeHeader($subject),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $host . '>',
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
            ];
            $headers = array_values(array_filter($headers, static fn($h) => $h !== null));
            $body = chunk_split(base64_encode($html . $cfg['logo_html']), 76, "\r\n");
            fwrite($fp, implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n");
            [$code, $text] = $this->read($fp);
            if ($code !== 250) {
                throw new RuntimeException('SMTP: el servidor rechazó el mensaje (' . $code . ') ' . preg_replace('/\s+/', ' ', $text));
            }
            @fwrite($fp, "QUIT\r\n");
        } finally {
            @fclose($fp);
        }
    }
}
