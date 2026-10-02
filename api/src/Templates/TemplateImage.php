<?php

declare(strict_types=1);

namespace DxFondito\Templates;

use DxFondito\Http\HttpException;
use DxFondito\Http\UploadedFile;

/**
 * An uploaded JPEG or PNG template image (system design, section 6.2).
 */
final class TemplateImage
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    // Larger images need more memory than PHP has on a shared host.
    public const MAX_SIDE = 4096;

    private function __construct(
        public readonly string $content,
        public readonly string $extension,
        public readonly int $width,
        public readonly int $height,
    ) {
    }

    /**
     * @throws HttpException 413 for a large file, 422 for a file that is not a JPEG or PNG image.
     */
    public static function fromUpload(UploadedFile $file): self
    {
        if ($file->isTooLarge() || $file->size > self::MAX_BYTES) {
            throw new HttpException(413, 'The image is larger than 10 MB');
        }
        if ($file->error !== UPLOAD_ERR_OK) {
            throw new HttpException(422, 'The upload of the image failed');
        }

        return self::fromContent($file->content());
    }

    /**
     * @throws HttpException 422 for content that is not a JPEG or PNG image, or a too large image.
     */
    public static function fromContent(string $content): self
    {
        $info = @getimagesizefromstring($content);
        $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
        if ($info === false || !isset($types[$info[2]])) {
            throw new HttpException(422, 'The image must be a JPEG or PNG image');
        }
        [$width, $height] = $info;
        if ($width > self::MAX_SIDE || $height > self::MAX_SIDE) {
            throw new HttpException(422, 'The image must have ' . self::MAX_SIDE . ' or fewer pixels on each side');
        }
        // The API opens each template with GD before it saves the file.
        $image = @imagecreatefromstring($content);
        if ($image === false) {
            throw new HttpException(422, 'The image must be a JPEG or PNG image');
        }

        return new self($content, $types[$info[2]], $width, $height);
    }
}
