<?php

declare(strict_types=1);

namespace DxFondito\Registry;

use RuntimeException;

final class CurlHttpClient implements HttpClient
{
    private const TIMEOUT = 60;

    /** @var array<string, string> */
    private array $cookies = [];

    public function request(string $url, ?array $form = null, ?string $caFile = null): string
    {
        $handle = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_USERAGENT => 'DxFondito-Registry/1.0',
            CURLOPT_HEADERFUNCTION => function ($handle, string $header): int {
                if (preg_match('/^Set-Cookie:\s*([^=;]+)=([^;]*)/i', $header, $match) === 1) {
                    $this->cookies[$match[1]] = $match[2];
                }

                return strlen($header);
            },
        ];
        if ($this->cookies !== []) {
            $options[CURLOPT_COOKIE] = implode('; ', array_map(
                static fn (string $name, string $value): string => $name . '=' . $value,
                array_keys($this->cookies),
                $this->cookies,
            ));
        }
        if ($form !== null) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($form);
        }
        if ($caFile !== null) {
            $options[CURLOPT_CAINFO] = $caFile;
        }
        curl_setopt_array($handle, $options);

        $body = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        if ($body === false) {
            throw new RuntimeException('The request to ' . parse_url($url, PHP_URL_HOST) . ' failed: ' . curl_error($handle));
        }
        if ($status !== 200) {
            throw new RuntimeException('The request to ' . parse_url($url, PHP_URL_HOST) . ' gave the status ' . $status);
        }

        return (string) $body;
    }
}
