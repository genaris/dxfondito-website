<?php

declare(strict_types=1);

namespace DxFondito\Tests\Registry;

use DxFondito\Registry\HttpClient;
use RuntimeException;

final class FakeHttpClient implements HttpClient
{
    /** @var list<array{url: string, form: array<string, string>|null, caFile: string|null}> */
    public array $requests = [];

    /**
     * @param array<string, string|list<string>> $responses The body for each address. A list gives one body for each request.
     */
    public function __construct(private array $responses)
    {
    }

    public function request(string $url, ?array $form = null, ?string $caFile = null): string
    {
        $this->requests[] = ['url' => $url, 'form' => $form, 'caFile' => $caFile];
        $response = $this->responses[$url] ?? throw new RuntimeException('The request to ' . $url . ' failed');
        if (is_array($response)) {
            return array_shift($this->responses[$url]) ?? throw new RuntimeException('No more responses');
        }

        return $response;
    }
}
