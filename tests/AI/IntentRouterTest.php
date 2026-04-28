declare(strict_types=1);

namespace App\Tests\AI;

use App\AI\Service\IntentRouter;
use App\AI\Domain\Enum\IntentType;
use App\AI\Contract\Message;
use App\AI\Contract\ToolInterface;
use App\AI\Contract\ToolDefinition;
use App\AI\Contract\User;
use PHPUnit\Framework\TestCase;

final class IntentRouterTest extends TestCase
{
    private IntentRouter $router;

    protected function setUp(): void
    {
        $this->router = new IntentRouter(new StubToolRegistry());
    }

    public function testClassifyReturnsGreetingForEmptyMessages(): void
    {
        $result = $this->router->classify([]);

        $this->assertEquals(IntentType::GREETING, $result);
    }

    public function testClassifyReturnsGreetingForHelloMessage(): void
    {
        $messages = [new Message('user', 'Hello there')];
        $result = $this->router->classify($messages);

        $this->assertEquals(IntentType::GREETING, $result);
    }

    public function testClassifyReturnsMutationForCreateMessage(): void
    {
        $messages = [new Message('user', 'Create a new job offer')];
        $result = $this->router->classify($messages);

        $this->assertEquals(IntentType::MUTATION, $result);
    }

    public function testClassifyReturnsScheduleForInterviewMessage(): void
    {
        $messages = [new Message('user', 'Schedule an interview')];
        $result = $this->router->classify($messages);

        $this->assertEquals(IntentType::SCHEDULE, $result);
    }

    public function testClassifyReturnsReportForStatsMessage(): void
    {
        $messages = [new Message('user', 'Show me the statistics')];
        $result = $this->router->classify($messages);

        $this->assertEquals(IntentType::REPORT, $result);
    }

    public function testSelectToolsReturnsEmptyArrayForGreeting(): void
    {
        $tools = $this->router->selectTools(IntentType::GREETING);

        $this->assertEmpty($tools);
    }

    public function testSelectToolsReturnsToolsForMutation(): void
    {
        $tools = $this->router->selectTools(IntentType::MUTATION);

        $this->assertNotEmpty($tools);
    }
}