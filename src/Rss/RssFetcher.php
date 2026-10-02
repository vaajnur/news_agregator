<?php

declare(strict_types=1);

namespace App\Rss;

use RuntimeException;
use SimpleXMLElement;

final class RssFetcher
{
    /** @param array{url:string,timeout:int,user_agent:string} $config */
    public function __construct(private readonly array $config)
    {
    }

    /** @return list<array{guid:string,title:string,link:string,description:?string,image_url:?string,category:string,published_at:string}> */
    public function fetch(): array
    {
        $xmlString = $this->download($this->config['url']);
        $xml = $this->parseXml($xmlString);

        $items = [];
        foreach ($xml->channel->item as $item) {
            $parsed = $this->parseItem($item);
            if ($parsed !== null) {
                $items[] = $parsed;
            }
        }

        return $items;
    }

    private function download(string $url): string
    {
        $attempts = 3;
        $lastError = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                if (function_exists('curl_init')) {
                    return $this->downloadWithCurl($url);
                }

                return $this->downloadWithStreams($url);
            } catch (RuntimeException $e) {
                $lastError = $e;
                if ($attempt < $attempts) {
                    usleep(250000 * $attempt);
                }
            }
        }

        throw $lastError ?? new RuntimeException('Failed to download RSS feed');
    }

    private function downloadWithCurl(string $url): string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_USERAGENT => $this->config['user_agent'],
            CURLOPT_HTTPHEADER => [
                'Accept: */*',
            ],
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false || $errno !== 0) {
            throw new RuntimeException(sprintf('cURL RSS download failed: %s', $error !== '' ? $error : 'unknown error'));
        }

        if ($status < 200 || $status >= 300 || $body === '') {
            throw new RuntimeException(sprintf('Unexpected RSS HTTP status: %d', $status));
        }

        return $body;
    }

    private function downloadWithStreams(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", [
                    'User-Agent: ' . $this->config['user_agent'],
                    'Accept: application/rss+xml, application/xml, text/xml, */*',
                    'Accept-Language: ru-RU,ru;q=0.9,en;q=0.8',
                ]),
                'timeout' => $this->config['timeout'],
                'follow_location' => 1,
                'max_redirects' => 3,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? 'no-response';

        if ($body === false || $body === '') {
            throw new RuntimeException(
                sprintf('Failed to download RSS feed: %s (%s)', $url, $statusLine)
            );
        }

        if (!preg_match('/\s2\d\d\s/', $statusLine)) {
            throw new RuntimeException(
                sprintf('Unexpected RSS HTTP status: %s', $statusLine)
            );
        }

        return $body;
    }

    private function parseXml(string $xmlString): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString, SimpleXMLElement::class, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false || !isset($xml->channel)) {
            throw new RuntimeException('Invalid RSS XML');
        }

        return $xml;
    }

    /** @return ?array{guid:string,title:string,link:string,description:?string,image_url:?string,category:string,published_at:string} */
    private function parseItem(SimpleXMLElement $item): ?array
    {
        $title = trim((string) $item->title);
        $link = trim((string) $item->link);
        $guid = trim((string) ($item->guid ?: $item->link));
        $pubDate = trim((string) $item->pubDate);
        $category = trim((string) ($item->category ?: 'Без категории'));

        if ($title === '' || $link === '' || $guid === '' || $pubDate === '') {
            return null;
        }

        $timestamp = strtotime($pubDate);
        if ($timestamp === false) {
            return null;
        }

        $description = trim((string) $item->description);
        $imageUrl = null;
        if (isset($item->enclosure['url'])) {
            $imageUrl = trim((string) $item->enclosure['url']) ?: null;
        }

        return [
            'guid' => mb_substr($guid, 0, 512),
            'title' => mb_substr($title, 0, 512),
            'link' => mb_substr($link, 0, 1024),
            'description' => $description !== '' ? $description : null,
            'image_url' => $imageUrl !== null ? mb_substr($imageUrl, 0, 1024) : null,
            'category' => mb_substr($category, 0, 255),
            'published_at' => date('Y-m-d H:i:s', $timestamp),
        ];
    }
}
