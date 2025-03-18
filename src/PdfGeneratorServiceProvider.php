<?php

namespace LeerTech\Tailwind;
use Illuminate\Support\ServiceProvider;

class PdfGeneratorServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'pdf-generator');

        $this->publishes([
            __DIR__.'/../config/pdf-generator.php' => config_path('pdf-generator.php'),
        ], 'config');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/pdf-generator.php', 'pdf-generator');
    }
}
