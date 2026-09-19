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
}
