<?php

namespace App\Service\Formation;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class QrCodeService
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function generateQrCode(string $token): string
    {
        // Prefer APP_PUBLIC_URL for mobile scanning (localhost is not reachable from phones).
        $path = $this->urlGenerator->generate('app_verify_certificate', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_PATH);
        $publicBaseUrl = (string) ($_ENV['APP_PUBLIC_URL'] ?? getenv('APP_PUBLIC_URL') ?: '');
        if ($publicBaseUrl === '') {
            $publicBaseUrl = (string) ($_ENV['SITE_BASE_URL'] ?? getenv('SITE_BASE_URL') ?: '');
        }

        // Handle accidental values like "=https://..." from env files.
        if (str_starts_with($publicBaseUrl, '=')) {
            $publicBaseUrl = ltrim($publicBaseUrl, '=');
        }

        $publicBaseUrl = rtrim($publicBaseUrl, '/');

        if ($publicBaseUrl !== '') {
            $url = $publicBaseUrl . $path;
        } else {
            $url = $this->urlGenerator->generate('app_verify_certificate', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $builder = new Builder(
            writer: new PngWriter(),
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 200,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin
        );

        $result = $builder->build();

        return $result->getDataUri();
    }
}