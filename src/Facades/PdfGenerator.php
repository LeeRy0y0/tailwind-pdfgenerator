<?php

namespace LeerTech\Tailwind\PdfGenerator\Facades;

use Illuminate\Support\Facades\Facade;

class PdfGenerator extends Facade
{
    /**
     * Returner den registrerede nøgle i service containeren.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'pdfgenerator';
    }
}
