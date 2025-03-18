<?php

namespace LeerTech\Tailwind\PdfGenerator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\File;

class PdfDocument
{
    protected string $view;
    protected array $data;
    protected string $filename;
    protected bool $download;
    protected ?string $footerView = null;

    public function __construct(string $view, array $data, string $filename, bool $download)
    {
        $this->view     = $view;
        $this->data     = $data;
        $this->filename = $filename;
        $this->download = $download;
    }

    public function footerView(string $footerView): self
    {
        $this->footerView = $footerView;
        return $this;
    }

    public function output()
    {
$scriptDir = base_path('vendor/leertech/tailwind-pdfgenerator/scripts');
$packageJsonPath = $scriptDir . DIRECTORY_SEPARATOR . 'package.json';
$puppeteerDir = $scriptDir . DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR . 'puppeteer';

// Opret en minimal package.json, hvis den ikke findes
if (!file_exists($packageJsonPath)) {
    $npmCmd = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'npm.cmd' : 'npm';
    $initCmd = "cd " . escapeshellarg($scriptDir) . " && {$npmCmd} init -y 2>&1";
    $initOutput = shell_exec($initCmd);
    \Log::debug("npm init output: " . $initOutput);
}

// Tjek om puppeteer er installeret
if (!file_exists($puppeteerDir)) {
    $npmCmd = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'npm.cmd' : 'npm';
    $installCmd = "cd " . escapeshellarg($scriptDir) . " && {$npmCmd} install puppeteer --no-save 2>&1";
    $installOutput = shell_exec($installCmd);
    \Log::debug("npm install puppeteer output: " . $installOutput);
}


        $html = View::make($this->view, $this->data)->render();

        $htmlFile = storage_path('app/pdf-temp.html');
        File::put($htmlFile, $html);

        if ($this->footerView) {
            $footerHtml = View::make($this->footerView)->render();
        } else {
            $defaultFooterPath = __DIR__ . '/../resources/footer.html';
            if (File::exists($defaultFooterPath)) {
                $footerHtml = File::get($defaultFooterPath);
            } else {
                $footerHtml = '';
            }
        }

        $footerFile = storage_path('app/pdf-footer-temp.html');
        File::put($footerFile, $footerHtml);

        $pdfFile = storage_path('app/' . $this->filename);

        $command = "node " . base_path('vendor/leertech/tailwind-pdfgenerator/scripts/generate-pdf.cjs') . " "
         . escapeshellarg($htmlFile) . " "
         . escapeshellarg($pdfFile) . " "
         . escapeshellarg($footerFile);

        $output = shell_exec($command);
        \Log::debug("PDF generation output: " . $output);


        if ($this->download) {
            return response()->download($pdfFile)->deleteFileAfterSend(true);
        }

        return $pdfFile;
    }
}
