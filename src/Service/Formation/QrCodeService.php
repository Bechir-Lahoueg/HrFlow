<?php

namespace App\Service\Formation;

use Endroid\QrCode\Builder\BuilderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class QrCodeService
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly BuilderInterface $certificateQrCodeBuilder
    ) {
    }

    public function generateQrCode(string $token): string
    {
        $path = $this->urlGenerator->generate('app_verify_certificate', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_PATH);
        $publicBaseUrl = (string) ($_ENV['APP_PUBLIC_URL'] ?? getenv('APP_PUBLIC_URL') ?: '');

        if ($publicBaseUrl === '') {
            $publicBaseUrl = (string) ($_ENV['SITE_BASE_URL'] ?? getenv('SITE_BASE_URL') ?: '');
        }

        if (str_starts_with($publicBaseUrl, '=')) {
            $publicBaseUrl = ltrim($publicBaseUrl, '=');
        }

        $publicBaseUrl = rtrim($publicBaseUrl, '/');

        if ($publicBaseUrl !== '') {
            $url = $publicBaseUrl . $path;
        } else {
            $url = $this->urlGenerator->generate('app_verify_certificate', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
        }


        $result = $this->certificateQrCodeBuilder->build(
            data: $url
        );

        return $result->getDataUri();
    }
}