<?php

declare(strict_types=1);

namespace DxFondito\Tests\Templates;

use DxFondito\Templates\CertificatePdf;
use PHPUnit\Framework\TestCase;

final class CertificatePdfTest extends TestCase
{
    public function testALandscapeImageGivesALandscapePageWithoutMargins(): void
    {
        $pdf = CertificatePdf::fromJpeg(self::jpeg(1600, 1131));

        self::assertStringStartsWith('%PDF-', $pdf);
        // 297 × 210 mm, in points of 1/72 inch.
        self::assertMatchesRegularExpression('#/MediaBox \[0 0 841\.89 595\.\d+\]#', $pdf);
    }

    public function testAPortraitImageGivesAPortraitPage(): void
    {
        $pdf = CertificatePdf::fromJpeg(self::jpeg(1000, 1414));

        self::assertMatchesRegularExpression('#/MediaBox \[0 0 595\.\d+ 841\.89\]#', $pdf);
    }

    private static function jpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($image);

        return (string) ob_get_clean();
    }
}
