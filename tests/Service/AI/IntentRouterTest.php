<?php

declare(strict_types=1);

namespace App\Tests\AI;

use App\AI\Domain\Enum\IntentType;
use App\AI\Infrastructure\ChatMessage;
use PHPUnit\Framework\TestCase;

class IntentRouterTest extends TestCase
{
    private \App\AI\Core\IntentRouter $router;

    protected function setUp(): void
    {
        $this->router = new \App\AI\Core\IntentRouter();
    }

    public function testClassifyGreetsAsGreeting(): void
    {
        $messages = [new ChatMessage('user', 'Bonjour')];
        $intent = $this->router->classify($messages);

        $this->assertSame(IntentType::GREETING, $intent);
    }

    public function testClassifyQueryKeywordsAsDataQuery(): void
    {
        $messages = [new ChatMessage('user', 'Donne-moi la liste des candidats')];
        $intent = $this->router->classify($messages);

        $this->assertSame(IntentType::DATA_QUERY, $intent);
    }

    public function testClassifyMutationKeywordsAsMutation(): void
    {
        $messages = [new ChatMessage('user', 'Modifie le statut de la candidature')];
        $intent = $this->router->classify($messages);

        $this->assertSame(IntentType::MUTATION, $intent);
    }

    public function testClassifyScheduleKeywordsAsSchedule(): void
    {
        $messages = [new ChatMessage('user', 'Planifie un entretien')];
        $intent = $this->router->classify($messages);

        $this->assertSame(IntentType::SCHEDULE, $intent);
    }

    public function testClassifyReportKeywordsAsReport(): void
    {
        $messages = [new ChatMessage('user', 'Génère un rapport')];
        $intent = $this->router->classify($messages);

        $this->assertSame(IntentType::REPORT, $intent);
    }
}