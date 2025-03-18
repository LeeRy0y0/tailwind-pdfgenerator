<?php

namespace LeerTech\Tailwind;

class PdfService
{
    public static function generateFromView(string $view, array $data = [], ?string $filename = null, bool $download = true)
    {
        $filename = $filename ?? 'document.pdf';
        return new PdfDocument($view, $data, $filename, $download);
    }
}
