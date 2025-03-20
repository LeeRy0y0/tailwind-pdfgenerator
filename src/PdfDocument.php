<?php

namespace LeerTech\Tailwind\PdfGenerator;
use Illuminate\Support\Facades\View;

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

    public function output()
    {
        $html = View::make($this->view, $this->data)->render();
        $pdfFile = storage_path('app/' . $this->filename);

        // Prepare footer template file if needed
        $footerTemplatePath = '';
        if ($this->footerView) {
            $footerHtml = View::make($this->footerView, $this->data)->render();
            $footerTemplatePath = tempnam(sys_get_temp_dir(), 'pdf-footer-') . '.html';
            file_put_contents($footerTemplatePath, $footerHtml);
        }

        $script = base_path('vendor/leertech/tailwind-pdfgenerator/scripts/generate-pdf.cjs');
        $node   = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'node.exe' : 'node';

        // Build options array to pass to the Node script
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

        // Build the command line. Pass pdfFile, then footer template path (if available), then the options JSON.
        $cmd = escapeshellcmd("$node $script " . escapeshellarg($pdfFile));
        if ($footerTemplatePath) {
            $cmd .= ' ' . escapeshellarg($footerTemplatePath);
        } else {
            // Pass an empty string so that the Node script's logic falls back to its default footer.
            $cmd .= ' ' . escapeshellarg('');
        }
        $cmd .= ' ' . $optionsJson;

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

        if ($footerTemplatePath && file_exists($footerTemplatePath)) {
            unlink($footerTemplatePath);
        }

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
