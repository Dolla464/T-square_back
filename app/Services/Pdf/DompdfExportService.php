<?php

namespace App\Services\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfInstance;

class DompdfExportService
{
    public function __construct(
        private ArabicPdfTextProcessor $textProcessor
    ) {}

    public function loadView(
        string $view,
        array $data = [],
        string $paper = 'a4',
        string $orientation = 'portrait'
    ): DompdfInstance {
        $processed = $this->textProcessor->process($data);

        return Pdf::loadView($view, $processed)->setPaper($paper, $orientation);
    }
}
