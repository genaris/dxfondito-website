<?php

declare(strict_types=1);

namespace DxFondito\Templates;

use FPDF;

/**
 * Puts the image of a certificate on one PDF page (FR-CER-5, system design section 6.3).
 */
final class CertificatePdf
{
    /** The long side of the page: the long side of an A4 sheet, in millimetres. */
    private const LONG_SIDE = 297.0;

    /**
     * The page has the proportions of the image, with no margin. A landscape image gives a landscape page.
     */
    public static function fromJpeg(string $jpeg): string
    {
        $info = getimagesizefromstring($jpeg);
        if ($info === false) {
            throw new \RuntimeException('The certificate image is not correct');
        }
        [$width, $height] = $info;
        $landscape = $width >= $height;
        $pageWidth = $landscape ? self::LONG_SIDE : self::LONG_SIDE * $width / $height;
        $pageHeight = $landscape ? self::LONG_SIDE * $height / $width : self::LONG_SIDE;

        // FPDF reads the image from a file.
        $path = tempnam(sys_get_temp_dir(), 'cer');
        if ($path === false) {
            throw new \RuntimeException('No temporary file for the certificate');
        }
        try {
            file_put_contents($path, $jpeg);
            $pdf = new FPDF($landscape ? 'L' : 'P', 'mm', [min($pageWidth, $pageHeight), max($pageWidth, $pageHeight)]);
            $pdf->SetMargins(0, 0);
            $pdf->SetAutoPageBreak(false);
            $pdf->SetCreator('Grupo DX Fondito');
            $pdf->AddPage();
            $pdf->Image($path, 0, 0, $pageWidth, $pageHeight, 'JPG');

            return $pdf->Output('S');
        } finally {
            unlink($path);
        }
    }
}
