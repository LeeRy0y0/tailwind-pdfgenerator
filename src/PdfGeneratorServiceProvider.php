<?php

namespace LeerTech\Tailwind\PdfGenerator;
use Illuminate\Support\ServiceProvider;

class PdfGeneratorServiceProvider extends ServiceProvider
{
public function boot()
{
    $this->loadViewsFrom(__DIR__.'/../resources/views', 'pdf-generator');
    $this->publishes([
        __DIR__.'/../config/pdf-generator.php' => config_path('pdf-generator.php'),
    ], 'config');

    // Kun til udviklingsformål: Kør en npm-kommando til at installere Puppeteer
    // Dette kan dog medføre betydelige opstartsforsinkelser og fejl, hvis npm ikke er tilgængelig.
    if (app()->environment('local')) {
        $scriptDir = base_path('vendor/leertech/tailwind-pdfgenerator/scripts');
        $packageJsonPath = $scriptDir . DIRECTORY_SEPARATOR . 'package.json';
        if (!file_exists($packageJsonPath)) {
            // Opret minimal package.json
            file_put_contents($packageJsonPath, json_encode([
                "name" => "pdf-generator-scripts",
                "version" => "1.0.0",
                "private" => true,
                "dependencies" => new \stdClass()
            ]));
        }
        $puppeteerDir = $scriptDir . DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR . 'puppeteer';
        if (!file_exists($puppeteerDir)) {
            $npmCmd = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'npm.cmd' : 'npm';
            $command = "cd " . escapeshellarg($scriptDir) . " && {$npmCmd} install puppeteer --no-save 2>&1";
            $output = shell_exec($command);
            \Log::debug("NPM install output: " . $output);
        }
    }
}

public function register()
{
    $this->mergeConfigFrom(__DIR__.'/../config/pdf-generator.php', 'pdf-generator');
}



}
