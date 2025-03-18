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
    // Render HTML direkte til en variabel
    $html = View::make($this->view, $this->data)->render();
    
    // Håndter footer som før, hvis nødvendigt – eller fjern hvis du ikke bruger den
    if ($this->footerView) {
        $footerHtml = View::make($this->footerView)->render();
    } else {
        $defaultFooterPath = __DIR__ . '/../resources/footer.html';
        $footerHtml = File::exists($defaultFooterPath) ? File::get($defaultFooterPath) : '';
    }
    // Hvis du ikke bruger footer, kan du fjerne footer-relateret logik.
    
    $pdfFile = storage_path('app/' . $this->filename);

    // Byg kommandoen – her antager vi, at vi kun behøver output-filen som argument.
    $scriptPath = base_path('vendor/leertech/tailwind-pdfgenerator/scripts/generate-pdf.cjs');
    $npmCmd = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'node.exe' : 'node';
    $command = $npmCmd . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($pdfFile);

    // Opsæt descriptor specification for proc_open() for at sende HTML via STDIN og modtage output
    $descriptorspec = [
        0 => ["pipe", "r"],  // STDIN til Node-processen
        1 => ["pipe", "w"],  // STDOUT (vi kan fange output, hvis nødvendigt)
        2 => ["pipe", "w"]   // STDERR
    ];

    $process = proc_open($command, $descriptorspec, $pipes, null, null);

    if (is_resource($process)) {
        // Send HTML-indholdet til Node-processen via STDIN
        fwrite($pipes[0], $html);
        fclose($pipes[0]);

        // Få fat i output (hvis du vil logge det)
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $return_value = proc_close($process);
        \Log::debug("Node script STDOUT: " . $stdout);
        \Log::debug("Node script STDERR: " . $stderr);
    }

    // Tjek, om PDF-filen er oprettet
    if ($this->download) {
        return response()->download($pdfFile)->deleteFileAfterSend(true);
    }

    return $pdfFile;
}

}
