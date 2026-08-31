<?php

namespace App\Support;

class XlsxSheetPath
{
    public static function zipEntry(string $relationshipTarget): string
    {
        $path = ltrim(str_replace('\\', '/', str_replace('../', '', $relationshipTarget)), '/');

        if ($path === '') {
            return 'xl/worksheets/sheet1.xml';
        }

        if (! str_starts_with($path, 'xl/')) {
            $path = 'xl/'.$path;
        }

        return $path;
    }
}
