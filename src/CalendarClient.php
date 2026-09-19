<?php

namespace Glider;

use DateTime;

class CalendarClient
{
    public function __construct(private readonly string $calendarUrl, private readonly string $username = '', private readonly string $password = '')
    {
    }

    public function fetchEvents(): array
    {
        if ($this->calendarUrl === '') {
            return [];
        }

        $ch = curl_init($this->calendarUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($this->username !== '' || $this->password !== '') {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        }

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false || $err !== '') {
            return [];
        }

        return $this->parseIcal($response);
    }

    private function parseIcal(string $raw): array
    {
        $lines = preg_split('/\r\n|\n|\r/', $raw) ?: [];
        $events = [];
        $current = [];

        foreach ($lines as $line) {
            if (str_starts_with($line, 'BEGIN:VEVENT')) {
                $current = [];
                continue;
            }

            if (str_starts_with($line, 'END:VEVENT')) {
                if (!empty($current)) {
                    $events[] = $current;
                }
                $current = [];
                continue;
            }

            if ($current === [] && !str_starts_with($line, 'BEGIN:')) {
                continue;
            }

            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $current[$key] = $value;
            }
        }

        foreach ($events as $index => $event) {
            $events[$index] = [
                'summary' => $event['SUMMARY'] ?? 'Ohne Titel',
                'start' => $this->parseDateTime($event['DTSTART'] ?? null),
                'end' => $this->parseDateTime($event['DTEND'] ?? null),
                'location' => $event['LOCATION'] ?? '',
            ];
        }

        return $events;
    }

    private function parseDateTime(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = strtoupper($value);
        $date = DateTime::createFromFormat('Ymd\THis', $normalized);

        if ($date === false) {
            $date = DateTime::createFromFormat('Ymd', $normalized);
        }

        if ($date === false) {
            return null;
        }

        return $date->format('Y-m-d H:i:s');
    }
}
