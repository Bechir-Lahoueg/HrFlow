<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class PingController
{
    #[Route('/ping', name: 'app_ping', methods: ['GET'])]
    public function ping(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok', 'ts' => time()]);
    }
}
