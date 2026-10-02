<?php

declare(strict_types=1);

namespace DxFondito\Registry;

interface HttpClient
{
    /**
     * A GET request, or a POST request with form fields. The client keeps the cookies between its requests.
     *
     * @param array<string, string>|null $form The fields of a POST request, or null for a GET request.
     * @param string|null $caFile The certificates for a server that does not send its intermediate certificate.
     * @throws \RuntimeException for a failed request or a status other than 200.
     */
    public function request(string $url, ?array $form = null, ?string $caFile = null): string;
}
