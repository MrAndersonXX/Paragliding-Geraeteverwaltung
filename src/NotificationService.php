<?php

namespace Glider;

class NotificationService
{
    public function __construct(private readonly array $mailConfig)
    {
    }

    public function send(string $to, string $subject, string $message): bool
    {
        $fromAddress = $this->mailConfig['from_address'] ?? 'tracker@example.com';
        $fromName = $this->mailConfig['from_name'] ?? 'Glider Equipment Tracker';

        $headers = [
            'From: ' . $fromName . ' <' . $fromAddress . '>',
            'Reply-To: ' . $fromAddress,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];

        if (($this->mailConfig['mailer'] ?? 'smtp') === 'smtp') {
            $smtpHost = $this->mailConfig['host'] ?? '';
            $smtpPort = (int) ($this->mailConfig['port'] ?? 587);
            $smtpUser = $this->mailConfig['username'] ?? '';
            $smtpPassword = $this->mailConfig['password'] ?? '';

            if ($smtpHost === '') {
                return false;
            }

            $transport = fsockopen($smtpHost, $smtpPort, $errno, $errstr, 10);
            if ($transport === false) {
                return false;
            }

            fclose($transport);

            return mail($to, $subject, $message, implode("\r\n", $headers));
        }

        return mail($to, $subject, $message, implode("\r\n", $headers));
    }

    public function testConnection(): array
    {
        $host = trim((string) ($this->mailConfig['host'] ?? ''));
        $port = (int) ($this->mailConfig['port'] ?? 587);
        $encryption = strtolower((string) ($this->mailConfig['encryption'] ?? 'tls'));
        $username = (string) ($this->mailConfig['username'] ?? '');
        $password = (string) ($this->mailConfig['password'] ?? '');

        if ($host === '') {
            return ['success' => false, 'message' => 'SMTP-Host ist nicht eingetragen.'];
        }

        $socketHost = $encryption === 'ssl' ? 'ssl://' . $host : $host;
        $socket = @fsockopen($socketHost, $port, $errno, $error, 10);
        if ($socket === false) {
            return ['success' => false, 'message' => "SMTP-Verbindung fehlgeschlagen: {$error} ({$errno})."];
        }

        stream_set_timeout($socket, 10);
        try {
            $greeting = $this->readResponse($socket);
            if (!$this->isResponse($greeting, 220)) {
                return ['success' => false, 'message' => 'SMTP-Server hat keine gültige Begrüßung gesendet.'];
            }

            $this->writeCommand($socket, 'EHLO glider-tracker');
            $ehlo = $this->readResponse($socket);
            if (!$this->isResponse($ehlo, 250)) {
                return ['success' => false, 'message' => 'SMTP-EHLO wurde abgelehnt.'];
            }

            if ($encryption === 'tls') {
                $this->writeCommand($socket, 'STARTTLS');
                $startTls = $this->readResponse($socket);
                if (!$this->isResponse($startTls, 220) || !stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    return ['success' => false, 'message' => 'TLS konnte mit dem SMTP-Server nicht aktiviert werden.'];
                }
                $this->writeCommand($socket, 'EHLO glider-tracker');
                if (!$this->isResponse($this->readResponse($socket), 250)) {
                    return ['success' => false, 'message' => 'SMTP-EHLO nach TLS wurde abgelehnt.'];
                }
            }

            if ($username !== '') {
                $this->writeCommand($socket, 'AUTH LOGIN');
                if (!$this->isResponse($this->readResponse($socket), 334)) {
                    return ['success' => false, 'message' => 'SMTP-Authentifizierung wird nicht akzeptiert.'];
                }
                $this->writeCommand($socket, base64_encode($username));
                if (!$this->isResponse($this->readResponse($socket), 334)) {
                    return ['success' => false, 'message' => 'SMTP-Benutzername wurde abgelehnt.'];
                }
                $this->writeCommand($socket, base64_encode($password));
                if (!$this->isResponse($this->readResponse($socket), 235)) {
                    return ['success' => false, 'message' => 'SMTP-Passwort wurde abgelehnt.'];
                }
            }

            $this->writeCommand($socket, 'QUIT');
            return ['success' => true, 'message' => 'SMTP-Verbindung und Zugangsdaten sind gültig.'];
        } finally {
            fclose($socket);
        }
    }

    private function writeCommand($socket, string $command): void
    {
        fwrite($socket, $command . "\r\n");
    }

    private function readResponse($socket): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }

    private function isResponse(string $response, int $code): bool
    {
        return str_starts_with($response, (string) $code);
    }
}
