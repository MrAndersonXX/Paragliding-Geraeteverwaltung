<?php

namespace Glider;

class ImageSearchService
{
    private const OPENVERSE_ENDPOINT = 'https://api.openverse.org/v1/images/';
    private const SERPAPI_ENDPOINT = 'https://serpapi.com/search.json';
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_BYTES = 8 * 1024 * 1024;
    private const CONTEXT_TERM = 'paraglider';

    /**
     * Finds candidate product photos: SerpApi (real Google Images results) when a key is configured,
     * falling back to the key-free Openverse API (openly licensed images) otherwise or on quota errors.
     *
     * @return array<int, array{title: string, thumbnail: string, link: string, contextLink: string}>
     */
    public static function searchImages(string $manufacturer, string $name, array $imageSearchConfig, int $limit = 8): array
    {
        if (empty($imageSearchConfig['enabled'])) {
            return [];
        }

        $manufacturer = self::sanitizeSearchTerm($manufacturer);
        $name = self::sanitizeSearchTerm($name);
        $serpApiKey = trim((string) ($imageSearchConfig['serpapi_key'] ?? ''));

        // Try the exact model first, then fall back to broader queries so a manufacturer-level
        // image is still found when no matching photo exists for the specific model/size.
        foreach (self::candidateQueries($manufacturer, $name) as $query) {
            if ($serpApiKey !== '') {
                $results = self::fetchSerpApiResults($query, $serpApiKey, $limit);
                if ($results !== []) {
                    return $results;
                }
            }
            $results = self::fetchOpenverseResults($query, $limit);
            if ($results !== []) {
                return $results;
            }
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private static function candidateQueries(string $manufacturer, string $name): array
    {
        $queries = [];
        if ($manufacturer !== '' && $name !== '') {
            $queries[] = mb_substr("$manufacturer $name " . self::CONTEXT_TERM, 0, 120);
            $firstWordOfName = strtok($name, ' ');
            if ($firstWordOfName !== false && $firstWordOfName !== $name) {
                $queries[] = mb_substr("$manufacturer $firstWordOfName " . self::CONTEXT_TERM, 0, 120);
            }
        }
        $subject = trim($manufacturer !== '' ? $manufacturer : $name);
        if ($subject !== '') {
            $queries[] = mb_substr("$subject " . self::CONTEXT_TERM, 0, 120);
        }
        return array_values(array_unique($queries));
    }

    /**
     * @return array<int, array{title: string, thumbnail: string, link: string, contextLink: string}>
     */
    private static function fetchSerpApiResults(string $query, string $apiKey, int $limit): array
    {
        if ($query === '') {
            return [];
        }

        $params = [
            'engine' => 'google_images',
            'q' => $query,
            'api_key' => $apiKey,
            'safe' => 'active',
            'ijn' => '0',
        ];

        $response = self::curlGetJson(self::SERPAPI_ENDPOINT . '?' . http_build_query($params));
        if ($response === null || !isset($response['images_results']) || !is_array($response['images_results'])) {
            return [];
        }

        $results = [];
        foreach (array_slice($response['images_results'], 0, $limit) as $item) {
            if (!is_array($item) || !empty($item['unsafe'])) {
                continue;
            }
            $link = (string) ($item['original'] ?? '');
            if (!self::isHttpsUrl($link)) {
                continue;
            }
            $results[] = [
                'title' => trim((string) ($item['title'] ?? '')),
                'thumbnail' => (string) ($item['thumbnail'] ?? $link),
                'link' => $link,
                'contextLink' => (string) ($item['link'] ?? ''),
            ];
        }

        return $results;
    }

    /**
     * @return array<int, array{title: string, thumbnail: string, link: string, contextLink: string}>
     */
    private static function fetchOpenverseResults(string $query, int $limit): array
    {
        if ($query === '') {
            return [];
        }

        $params = [
            'q' => $query,
            'page_size' => (string) max(1, min($limit, 20)),
            // Openverse excludes results flagged as sensitive/mature by default.
            'mature' => 'false',
        ];

        $response = self::curlGetJson(self::OPENVERSE_ENDPOINT . '?' . http_build_query($params));
        if ($response === null || !isset($response['results']) || !is_array($response['results'])) {
            return [];
        }

        $results = [];
        foreach ($response['results'] as $item) {
            if (!is_array($item) || !empty($item['mature'])) {
                continue;
            }
            $link = (string) ($item['url'] ?? '');
            if (!self::isHttpsUrl($link)) {
                continue;
            }
            $results[] = [
                'title' => trim((string) ($item['title'] ?? '')),
                'thumbnail' => (string) ($item['thumbnail'] ?? $link),
                'link' => $link,
                'contextLink' => (string) ($item['foreign_landing_url'] ?? ''),
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
