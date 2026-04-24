<?php

namespace App\Service\Shared;

use Symfony\Component\Process\Process;

final class MedicalCertificateOcrService
{
    public function __construct(
        private readonly AiService $aiService,
    ) {
    }

    /** @return array{text: ?string, summary: ?string} */
    public function extractFromAttachment(string $absolutePath, string $mimeType): array
    {
        $mimeType = strtolower(trim($mimeType));
        $text = '';

        if (in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
            $text = $this->aiService->extractMedicalCertificateTextFromImage($absolutePath, $mimeType);
        } elseif ($mimeType === 'application/pdf') {
            $text = $this->extractPdfText($absolutePath);
        }

        $text = trim((string) $text);
        if ($text === '') {
            return ['text' => null, 'summary' => null];
        }

        $summary = trim($this->aiService->summarizeMedicalCertificateContext($text));

        return [
            'text' => mb_substr($text, 0, 15000),
            'summary' => $summary !== '' ? mb_substr($summary, 0, 2000) : null,
        ];
    }

    private function extractPdfText(string $absolutePath): string
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return '';
        }

        try {
            $process = new Process(['pdftotext', '-layout', $absolutePath, '-']);
            $process->setTimeout(20);
            $process->run();

            if (!$process->isSuccessful()) {
                return '';
            }

            return trim($process->getOutput());
        } catch (\Throwable) {
            return '';
        }
    }
}
