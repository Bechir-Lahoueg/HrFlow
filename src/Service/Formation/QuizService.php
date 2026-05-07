<?php

namespace App\Service\Formation;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class QuizService
{
    private HttpClientInterface $httpClient;
    private string $apiKey = 'qa_sk_d3e548b8df478e3f1458c00186dc8476a7d160c5';

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Récupère des questions basées sur un tag (Java, PHP, MySQL, etc.)
     */
    /** @return array<mixed> */
    public function getQuestions(string $tag, int $limit = 10, string $difficulty = 'Medium'): array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://quizapi.io/api/v1/questions', [
                'query' => [
                    'apiKey' => $this->apiKey,
                    'tags' => $tag,
                    'limit' => $limit,
                    'difficulty' => $difficulty,
                ],
            ]);

            return $response->toArray();
        } catch (\Exception $e) {
            // En cas d'erreur (limite d'API, réseau), on retourne un tableau vide
            return [];
        }
    }

    /**
     * Une méthode spécifique pour vos besoins HR-Flow
     */
    /** @return array<mixed> */
    public function getTechnicalQuiz(string $technology): array
    {
        // On peut mapper les technos internes vers les tags de l'API
        $tagMap = [
            'symfony' => 'PHP',
            'java' => 'Java',
            'mysql' => 'MySQL',
            'devops' => 'Docker'
        ];

        $tag = $tagMap[strtolower($technology)] ?? $technology;

        return $this->getQuestions($tag);
    }
}