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
        $html = View::make($this->view, $this->data)->render();
        $pdfFile = storage_path('app/' . $this->filename);

        $script = base_path('vendor/leertech/tailwind-pdfgenerator/scripts/generate-pdf.cjs');
        $node   = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'node.exe' : 'node';
        $cmd    = escapeshellcmd("$node $script " . escapeshellarg($pdfFile));

        $descriptors = [
            0 => ['pipe', 'r'],  // STDIN
            1 => ['pipe', 'w'],  // STDOUT
            2 => ['pipe', 'w'],  // STDERR
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

        if ($return !== 0) {
            \Log::error("PDF generation fejl: $stderr");
            throw new \RuntimeException("PDF generation mislykkedes: $stderr");
        }

        if ($this->download) {
            return response()->download($pdfFile)->deleteFileAfterSend(true);
        }

        return $pdfFile;
    }
}