declare(strict_types=1);

namespace App\Tests\AI\Stubs;

use App\AI\Contract\LlmClientInterface;
use App\AI\Contract\ChatRequest;
use App\AI\Contract\ChatResponse;

final class InMemoryLlmClient implements LlmClientInterface
{
    public function chat(ChatRequest $request): ChatResponse
    {
        return new ChatResponse(text: 'Hello! How can I help you?');
    }
}