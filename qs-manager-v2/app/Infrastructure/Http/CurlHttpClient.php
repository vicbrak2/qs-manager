<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Http;

final class CurlHttpClient implements HttpClient
{
    /**
     * @param array<string, mixed> $options
     * @return array{statusCode: int, contentType: string, contents: string|false, error?: string}
     */
    public function get(string $url, array $options = []): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $options['connect_timeout'] ?? 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, $options['timeout'] ?? 15);
        
        if (isset($options['headers'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $options['headers']);
        }
        
        $contents = curl_exec($ch);
        
        if ($contents === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return [
                'statusCode' => 0,
                'contentType' => '',
                'contents' => false,
                'error' => $error,
            ];
        }
        
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
        curl_close($ch);
        
        return [
            'statusCode' => $statusCode,
            'contentType' => $contentType,
            'contents' => $contents,
        ];
    }
}
