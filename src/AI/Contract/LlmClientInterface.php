<?php

declare(strict_types=1);

namespace App\AI\Contract;

use App\AI\Infrastructure\ChatRequest;
use App\AI\Infrastructure\ChatResponse;

interface LlmClientInterface
{
    public function chat(ChatRequest $request): ChatResponse;
}