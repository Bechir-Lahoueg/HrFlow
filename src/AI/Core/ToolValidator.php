<?php

declare(strict_types=1);

namespace App\AI\Core;

use App\AI\Infrastructure\ToolCall;

final class ToolValidator
{
    public function validate(ToolCall $toolCall, object $user): void
    {
        if ($toolCall->name === '') {
            throw new \InvalidArgumentException('Tool name cannot be empty');
        }

        if (\count($toolCall->arguments) === 0) {
            return;
        }

        $forbiddenKeys = ['password', 'secret', 'token', 'api_key'];
        foreach ($forbiddenKeys as $key) {
            if (isset($toolCall->arguments[$key]) && !empty($toolCall->arguments[$key])) {
                throw new \InvalidArgumentException("Argument '{$key}' is not allowed");
            }
        }
    }
}