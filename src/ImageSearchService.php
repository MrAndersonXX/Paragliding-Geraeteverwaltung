<?php

namespace Glider;

class ImageSearchService
{
    private const ENDPOINT = 'https://www.googleapis.com/customsearch/v1';
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_BYTES = 8 * 1024 * 1024;
    private const CONTEXT_TERMS = 'Gleitschirm Paragliding Ausrüstung';

    /**
     * @return array<int, array{title: string, thumbnail: string, link: string, contextLink: string}>
     */
    public static function searchImages(string $manufacturer, string $name, array $imageSearchConfig, int $limit = 8): array
    {
        $apiKey = trim((string) ($imageSearchConfig['api_key'] ?? ''));
        $cseId = trim((string) ($imageSearchConfig['cse_id'] ?? ''));
        if (empty($imageSearchConfig['enabled']) || $apiKey === '' || $cseId === '') {
            return [];
        }

        $query = self::buildQuery($manufacturer, $name);
        if ($query === '') {
            return [];
        }

        $params = [
            'key' => $apiKey,
            'cx' => $cseId,
            'q' => $query,
            'searchType' => 'image',
            'safe' => 'high',
            'imgType' => 'photo',
            'num' => (string) max(1, min($limit, 10)),
        ];

        $response = self::curlGetJson(self::ENDPOINT . '?' . http_build_query($params));
        if ($response === null || !isset($response['items']) || !is_array($response['items'])) {
            return [];
        }

        $results = [];
        foreach ($response['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $link = (string) ($item['link'] ?? '');
            if (!self::isHttpsUrl($link)) {
                continue;
            }
            $results[] = [
                'title' => trim((string) ($item['title'] ?? '')),
                'thumbnail' => (string) ($item['image']['thumbnailLink'] ?? $link),
                'link' => $link,
                'contextLink' => (string) ($item['image']['contextLink'] ?? ''),
            ];
        }

        return $results;
    }

    /**
     * Downloads a URL and validates it is a safe, correctly-typed image. Throws on any failure.
     *
     * @return array{tmpPath: string, mime: string, size: int, extension: string}
     */
    public static function downloadAndValidate(string $url): array
    {
        if (!self::isHttpsUrl($url)) {
            throw new \RuntimeException('Nur https-URLs sind erlaubt.');
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '' || self::hostResolvesToPrivateIp($host)) {
            throw new \RuntimeException('Die Bild-URL verweist auf ein nicht erlaubtes Ziel.');
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'imgfetch_');
        if ($tmpPath === false) {
            throw new \RuntimeException('Temporäre Datei konnte nicht erstellt werden.');
        }

        $handle = fopen($tmpPath, 'wb');
        if ($handle === false) {
            @unlink($tmpPath);
            throw new \RuntimeException('Temporäre Datei konnte nicht geöffnet werden.');
        }

        $downloadedBytes = 0;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 1,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'GliderEquipmentTracker/1.0',
            CURLOPT_WRITEFUNCTION => function ($curlHandle, $chunk) use ($handle, &$downloadedBytes) {
                $downloadedBytes += strlen($chunk);
                if ($downloadedBytes > self::MAX_BYTES) {
                    return -1;
                }
                return fwrite($handle, $chunk);
            },
        ]);
        $success = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);
        fclose($handle);

        if ($success === false || $downloadedBytes === 0 || $downloadedBytes > self::MAX_BYTES) {
            @unlink($tmpPath);
            throw new \RuntimeException('Bild konnte nicht heruntergeladen werden: ' . $curlError);
        }

        $imageInfo = @getimagesize($tmpPath);
        $mime = $imageInfo['mime'] ?? (function_exists('mime_content_type') ? mime_content_type($tmpPath) : false);
        if ($imageInfo === false || $mime === false || !in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            @unlink($tmpPath);
            throw new \RuntimeException('Die Datei ist kein gültiges Bild.');
        }

        return [
            'tmpPath' => $tmpPath,
            'mime' => $mime,
            'size' => $downloadedBytes,
            'extension' => self::extensionForMime($mime),
        ];
    }

    private static function buildQuery(string $manufacturer, string $name): string
    {
        $manufacturer = self::sanitizeSearchTerm($manufacturer);
        $name = self::sanitizeSearchTerm($name);
        $subject = trim($manufacturer . ' ' . $name);
        if ($subject === '') {
            return '';
        }

        return mb_substr($subject . ' ' . self::CONTEXT_TERMS, 0, 120);
    }

    private static function sanitizeSearchTerm(string $term): string
    {
        // Strip search-operator characters so user-supplied names cannot hijack query semantics.
        $term = preg_replace('/[^\p{L}\p{N} .\-]/u', ' ', $term) ?? '';
        $term = preg_replace('/\s+/', ' ', $term) ?? '';
        return trim(mb_substr(trim($term), 0, 60));
    }

    private static function isHttpsUrl(string $url): bool
    {
        return str_starts_with($url, 'https://') && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private static function hostResolvesToPrivateIp(string $host): bool
    {
        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $records = @dns_get_record($host, DNS_A + DNS_AAAA);
            if ($records === false) {
                return true;
            }
            foreach ($records as $record) {
                $ips[] = $record['ip'] ?? $record['ipv6'] ?? '';
            }
        }

        if ($ips === []) {
            return true;
        }

        foreach ($ips as $ip) {
            if ($ip === '' || !filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            )) {
                return true;
            }
        }

        return false;
    }

    private static function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    private static function curlGetJson(string $url): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT => 'GliderEquipmentTracker/1.0',
        ]);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $httpCode >= 400) {
            return null;
        }

        $decoded = json_decode((string) $body, true);
        return is_array($decoded) ? $decoded : null;
    }
}
