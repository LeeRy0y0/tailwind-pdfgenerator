<?php

namespace LeerTech\Tailwind\PdfGenerator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class PdfDocument
{
    protected string $view;
    protected array $data;
    protected string $filename;
    protected bool $download;
    protected ?string $footerView = null;
    
    // New properties for size and orientation
    protected string $format = 'A4';
    protected bool $landscape = false;

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
    
    // Set the page format/size (e.g., "A5", "A4")
    public function format(string $format): self
    {
        $this->format = $format;
        return $this;
    }
    
    // Set the orientation: "portrait" or "landscape"
    public function orientation(string $orientation): self
    {
        $this->landscape = (strtolower($orientation) === 'landscape');
        return $this;
    }

    public function render(): string
    {
        $html = View::make($this->view, $this->data)->render();
        $pdfFile = storage_path('app/' . $this->filename);

        // Prepare footer
        $footerTemplatePath = '';
        if ($this->footerView) {
            $footerHtml = View::make($this->footerView, $this->data)->render();
            $footerTemplatePath = tempnam(sys_get_temp_dir(), 'pdf-footer-') . '.html';
            file_put_contents($footerTemplatePath, $footerHtml);
        }

        $script = base_path('vendor/leertech/tailwind-pdfgenerator/scripts/generate-pdf.cjs');
        $node   = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'node.exe' : 'node';

        $options = [
            'format'    => $this->format,
            'landscape' => $this->landscape,
            'margin'    => [
                'top'    => "10mm",
                'bottom' => "20mm",
                'left'   => "10mm",
                'right'  => "10mm"
            ]
        ];
        $optionsJson = escapeshellarg(json_encode($options));

        $cmd = escapeshellcmd("$node $script " . escapeshellarg($pdfFile));
        $cmd .= ' ' . escapeshellarg($footerTemplatePath ?: '');
        $cmd .= ' ' . $optionsJson;

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new \RuntimeException('Kunne ikke starte Node-processen');
        }

        fwrite($pipes[0], $html);
        fclose($pipes[0]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $return = proc_close($process);

        if ($footerTemplatePath && file_exists($footerTemplatePath)) {
            unlink($footerTemplatePath);
        }

        if ($return !== 0) {
            \Log::error("PDF generation fejl: $stderr");
            throw new \RuntimeException("PDF generation mislykkedes: $stderr");
        }

        return $pdfFile;
    }


    public function output()
    {
        $pdfFile = $this->render();

        if ($this->download) {
            return response()->download($pdfFile)->deleteFileAfterSend(true);
        }

        return file_get_contents($pdfFile);
    }

    public function saveToTemp(): string
    {
        $html = View::make($this->view, $this->data)->render();
        $filename = $this->filename ?? Str::random(16) . '.pdf';
    
        $pdfTempPath = storage_path('app/temp/' . $filename);
    
        if (!file_exists(dirname($pdfTempPath))) {
            mkdir(dirname($pdfTempPath), 0755, true);
        }

        $footerTemplatePath = '';
        if ($this->footerView) {
            $footerHtml = View::make($this->footerView, $this->data)->render();
            $footerTemplatePath = tempnam(sys_get_temp_dir(), 'pdf-footer-') . '.html';
            file_put_contents($footerTemplatePath, $footerHtml);
        }
    
        $scriptPath = base_path('vendor/leertech/tailwind-pdfgenerator/scripts/generate-pdf.cjs');
        $nodeBinary = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'node.exe' : 'node';
    
        $options = [
            'format'    => $this->format ?? 'A4',
            'landscape' => $this->landscape ?? false,
            'margin'    => [
                'top'    => '10mm',
                'bottom' => '20mm',
                'left'   => '10mm',
                'right'  => '10mm',
            ],
        ];
    
        $process = new Process([
            $nodeBinary,
            $scriptPath,
            $pdfTempPath,
            $footerTemplatePath ?: '',
            json_encode($options),
        ]);
    
        $process->setInput($html);
        $process->run();
    
        if ($footerTemplatePath && file_exists($footerTemplatePath)) {
            unlink($footerTemplatePath);
        }

        if (!$process->isSuccessful()) {
            \Log::error("PDF generation fejl: " . $process->getErrorOutput());
            throw new \RuntimeException("PDF generation mislykkedes: " . $process->getErrorOutput());
        }
    
        $relativePath = 'temp/' . $filename;
        $publicPath = storage_path('app/public/' . $relativePath);
    
        if (!file_exists(dirname($publicPath))) {
            mkdir(dirname($publicPath), 0755, true);
        }
    
        copy($pdfTempPath, $publicPath);
        unlink($pdfTempPath);

        return Storage::disk('public')->url($relativePath);
    }
}
