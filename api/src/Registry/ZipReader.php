<?php

declare(strict_types=1);

namespace DxFondito\Registry;

use RuntimeException;

/**
 * Reads one file of a ZIP archive, such as content.xml of an ODS file. The shared host can lack the zip
 * extension of PHP. The zlib extension inflates the data.
 */
final class ZipReader
{
    /**
     * @throws RuntimeException if the archive is not correct or does not have the file.
     */
    public static function read(string $archive, string $name): string
    {
        // The end of central directory record: the last 22 bytes, or more with a comment.
        $end = strrpos($archive, "PK\x05\x06");
        if ($end === false) {
            throw new RuntimeException('The file is not a ZIP archive');
        }
        $directory = unpack('ventries/Vsize/Voffset', substr($archive, $end + 10, 10));
        $position = $directory['offset'];
        for ($i = 0; $i < $directory['entries']; $i++) {
            if (substr($archive, $position, 4) !== "PK\x01\x02") {
                throw new RuntimeException('The ZIP directory is not correct');
            }
            $entry = unpack('vmethod', substr($archive, $position + 10, 2))
                + unpack('Vcompressed', substr($archive, $position + 20, 4))
                + unpack('vnameLength/vextraLength/vcommentLength', substr($archive, $position + 28, 6))
                + unpack('Vlocal', substr($archive, $position + 42, 4));
            $entryName = substr($archive, $position + 46, $entry['nameLength']);
            if ($entryName === $name) {
                return self::data($archive, $entry);
            }
            $position += 46 + $entry['nameLength'] + $entry['extraLength'] + $entry['commentLength'];
        }

        throw new RuntimeException('The ZIP archive does not have ' . $name);
    }

    /**
     * @param array{method: int, compressed: int, local: int} $entry
     */
    private static function data(string $archive, array $entry): string
    {
        $local = $entry['local'];
        $lengths = unpack('vnameLength/vextraLength', substr($archive, $local + 26, 4));
        $start = $local + 30 + $lengths['nameLength'] + $lengths['extraLength'];
        $data = substr($archive, $start, $entry['compressed']);

        if ($entry['method'] === 0) {
            return $data;
        }
        if ($entry['method'] === 8) {
            $inflated = @gzinflate($data);
            if ($inflated === false) {
                throw new RuntimeException('The ZIP data is not correct');
            }

            return $inflated;
        }

        throw new RuntimeException('The ZIP compression method is not known');
    }
}
