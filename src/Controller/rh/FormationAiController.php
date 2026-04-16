<?php

namespace App\Controller\rh;

use App\Service\AiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class FormationAiController extends AbstractController
{
    #[Route('/rh/api/formation/generate-ai-objectifs', name: 'rh_formation_generate_ai', methods: ['POST'])]
    public function generateAi(Request $request, AiService $aiService): Response
    {
        $title = trim((string) $request->request->get('titre', ''));
        if (!$title) {
            return $this->json(['error' => 'Le titre est requis'], 400);
        }

        $context = [
            'titre' => $title,
            'type' => trim((string) $request->request->get('type', '')),
            'duree' => trim((string) $request->request->get('duree', '')),
            'organisme' => trim((string) $request->request->get('organisme', '')),
            'description' => trim((string) $request->request->get('description', '')),
        ];

        try {
            $objectifs = $aiService->generateObjectives($context);
            return $this->json(['objectifs' => $objectifs]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Exception: ' . $e->getMessage()], 500);
        }
    }
}

