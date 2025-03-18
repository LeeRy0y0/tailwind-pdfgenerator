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

        $command = "node " . base_path('packages/LeerTech/Tailwind/scripts/generate-pdf.cjs') . " "
                 . escapeshellarg($htmlFile) . " "
                 . escapeshellarg($pdfFile) . " "
                 . escapeshellarg($footerFile);

        shell_exec($command);

        if ($this->download) {
            return response()->download($pdfFile)->deleteFileAfterSend(true);
        }

        return $pdfFile;
    }
}
