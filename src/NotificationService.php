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
        [$socket, $error] = $this->openAuthenticatedSocket();
        if ($socket === false) {
            return ['success' => false, 'message' => $error];
        }

        try {
            $this->writeCommand($socket, 'QUIT');
            return ['success' => true, 'message' => 'SMTP-Verbindung und Zugangsdaten sind gültig.'];
        } finally {
            fclose($socket);
        }
    }

    public function sendTestEmail(string $to): array
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Bitte eine gültige Empfängeradresse eintragen.'];
        }

        $from = trim((string) ($this->mailConfig['from_address'] ?? ''));
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Für den Versand muss eine gültige Absenderadresse eingetragen sein.'];
        }

        [$socket, $error] = $this->openAuthenticatedSocket();
        if ($socket === false) {
            return ['success' => false, 'message' => $error];
        }

        try {
            $this->writeCommand($socket, 'MAIL FROM:<' . $from . '>');
            if (!$this->isResponse($this->readResponse($socket), 250)) {
                return ['success' => false, 'message' => 'SMTP hat die Absenderadresse abgelehnt.'];
            }
            $this->writeCommand($socket, 'RCPT TO:<' . $to . '>');
            if (!$this->isResponse($this->readResponse($socket), 250, 251)) {
                return ['success' => false, 'message' => 'SMTP hat die Empfängeradresse abgelehnt.'];
            }
            $this->writeCommand($socket, 'DATA');
            if (!$this->isResponse($this->readResponse($socket), 354)) {
                return ['success' => false, 'message' => 'SMTP hat den Nachrichtenversand abgelehnt.'];
            }

            $fromName = (string) ($this->mailConfig['from_name'] ?? 'Glider Equipment Tracker');
            $body = "Dies ist eine Test-E-Mail des Glider Equipment Trackers.\r\n\r\nDer SMTP-Versand funktioniert.";
            $headers = 'From: ' . $fromName . ' <' . $from . ">\r\n" .
                'To: ' . $to . "\r\n" .
                'Subject: SMTP-Test Glider Equipment Tracker' . "\r\n" .
                'MIME-Version: 1.0' . "\r\n" .
                'Content-Type: text/plain; charset=UTF-8' . "\r\n\r\n";
            $body = preg_replace('/^\./m', '..', $headers . $body) . "\r\n.\r\n";
            fwrite($socket, $body);
            if (!$this->isResponse($this->readResponse($socket), 250)) {
                return ['success' => false, 'message' => 'SMTP hat die Test-E-Mail nicht angenommen.'];
            }

            $this->writeCommand($socket, 'QUIT');
            return ['success' => true, 'message' => 'Test-E-Mail wurde erfolgreich versendet.'];
        } finally {
            fclose($socket);
        }
    }

    private function openAuthenticatedSocket(): array
    {
        $host = trim((string) ($this->mailConfig['host'] ?? ''));
        $port = (int) ($this->mailConfig['port'] ?? 587);
        $encryption = strtolower((string) ($this->mailConfig['encryption'] ?? 'tls'));
        $username = (string) ($this->mailConfig['username'] ?? '');
        $password = (string) ($this->mailConfig['password'] ?? '');

        if ($host === '') {
            return [false, 'SMTP-Host ist nicht eingetragen.'];
        }

        $socketHost = $encryption === 'ssl' ? 'ssl://' . $host : $host;
        $socket = @fsockopen($socketHost, $port, $errno, $error, 10);
        if ($socket === false) {
            return [false, "SMTP-Verbindung fehlgeschlagen: {$error} ({$errno})."];
        }
        stream_set_timeout($socket, 10);

        $greeting = $this->readResponse($socket);
        if (!$this->isResponse($greeting, 220)) {
            $message = $this->responseError('SMTP-Begrüßung', $greeting, $socket);
            fclose($socket);
            return [false, $message];
        }
        $this->writeCommand($socket, 'EHLO glider-tracker');
        if (!$this->isResponse($this->readResponse($socket), 250)) {
            fclose($socket);
            return [false, 'SMTP-EHLO wurde abgelehnt.'];
        }
        if ($encryption === 'tls') {
            $this->writeCommand($socket, 'STARTTLS');
            if (!$this->isResponse($this->readResponse($socket), 220) || !stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return [false, 'TLS konnte mit dem SMTP-Server nicht aktiviert werden.'];
            }
            $this->writeCommand($socket, 'EHLO glider-tracker');
            if (!$this->isResponse($this->readResponse($socket), 250)) {
                fclose($socket);
                return [false, 'SMTP-EHLO nach TLS wurde abgelehnt.'];
            }
        }
        if ($username !== '') {
            $this->writeCommand($socket, 'AUTH LOGIN');
            if (!$this->isResponse($this->readResponse($socket), 334)) {
                fclose($socket);
                return [false, 'SMTP-Authentifizierung wird nicht akzeptiert.'];
            }
            $this->writeCommand($socket, base64_encode($username));
            if (!$this->isResponse($this->readResponse($socket), 334)) {
                fclose($socket);
                return [false, 'SMTP-Benutzername wurde abgelehnt.'];
            }
            $this->writeCommand($socket, base64_encode($password));
            if (!$this->isResponse($this->readResponse($socket), 235)) {
                fclose($socket);
                return [false, 'SMTP-Passwort wurde abgelehnt.'];
            }
        }
        return [$socket, ''];
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

    private function isResponse(string $response, int ...$codes): bool
    {
        foreach ($codes as $code) {
            if (str_starts_with($response, (string) $code)) {
                return true;
            }
        }
        return false;
    }

    private function responseError(string $step, string $response, $socket): string
    {
        $meta = stream_get_meta_data($socket);
        if (($meta['timed_out'] ?? false) || $response === '') {
            return "{$step} fehlgeschlagen: keine Antwort innerhalb von 10 Sekunden. Prüfe Host, Port und Verschlüsselung (587/TLS oder 465/SSL).";
        }

        $line = trim(strtok($response, "\r\n"));
        $line = preg_replace('/[^ -~]/', '', $line) ?: 'unbekannte Antwort';
        return "{$step} fehlgeschlagen. SMTP-Server antwortete: " . substr($line, 0, 160);
    }
}
