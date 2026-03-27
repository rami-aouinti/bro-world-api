<?php

declare(strict_types=1);

namespace App\Library\Domain\Enum;

use function in_array;
use function str_contains;
use function strtolower;

enum LibraryFileType: string
{
    case IMAGE = 'image';
    case PDF = 'pdf';
    case EXCEL = 'excel';
    case WORD = 'word';
    case VIDEO = 'video';
    case OTHER = 'other';

    public static function detect(string $mimeType, string $extension): self
    {
        $mime = strtolower($mimeType);
        $ext = strtolower($extension);

        if (str_contains($mime, 'image/') || in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) {
            return self::IMAGE;
        }

        if ($mime === 'application/pdf' || $ext === 'pdf') {
            return self::PDF;
        }

        if (
            in_array($mime, [
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/csv',
            ], true)
            || in_array($ext, ['xls', 'xlsx', 'csv'], true)
        ) {
            return self::EXCEL;
        }

        if (
            in_array($mime, [
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ], true)
            || in_array($ext, ['doc', 'docx'], true)
        ) {
            return self::WORD;
        }

        if (str_contains($mime, 'video/') || in_array($ext, ['mp4', 'mov', 'avi', 'mkv', 'webm'], true)) {
            return self::VIDEO;
        }

        return self::OTHER;
    }
}
