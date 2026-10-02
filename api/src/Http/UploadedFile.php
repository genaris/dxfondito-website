<?php

declare(strict_types=1);

namespace DxFondito\Http;

/**
 * A file of a multipart request.
 */
final class UploadedFile
{
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly int $size,
        public readonly int $error = UPLOAD_ERR_OK,
    ) {
    }

    public function isTooLarge(): bool
    {
        return $this->error === UPLOAD_ERR_INI_SIZE || $this->error === UPLOAD_ERR_FORM_SIZE;
    }

    public function content(): string
    {
        return (string) file_get_contents($this->path);
    }
}
