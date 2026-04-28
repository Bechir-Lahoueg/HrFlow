declare(strict_types=1);

namespace App\Tests\AI;

use App\AI\Service\ChangesetManager;
use App\AI\Contract\ToolCall;
use App\AI\Contract\User;
use App\AI\Domain\ValueObject\PendingChangeset;
use App\AI\Domain\Enum\ChangesetStatus;
use PHPUnit\Framework\TestCase;

final class ChangesetManagerTest extends TestCase
{
    private ChangesetManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ChangesetManager(new InMemoryChangesetStorage());
    }

    public function testStageCreatesPendingChangeset(): void
    {
        $toolCall = new ToolCall('create_job_offer', [
            'applicationId' => 'app-123',
            'candidateEmail' => 'test@example.com',
        ]);

        $user = new User(id: 1, email: 'test@example.com');

        $changeset = $this->manager->stage($toolCall, ['result' => 'ok'], $user);

        $this->assertInstanceOf(PendingChangeset::class, $changeset);
        $this->assertEquals(ChangesetStatus::PENDING, $changeset->status);
    }

    public function testConfirmChangeset(): void
    {
        $toolCall = new ToolCall('create_job_offer', [
            'applicationId' => 'app-123',
        ]);

        $user = new User(id: 1, email: 'test@example.com');

        $changeset = $this->manager->stage($toolCall, ['result' => 'ok'], $user);

        $confirmed = $this->manager->confirm($changeset->id, $user);

        $this->assertEquals(ChangesetStatus::CONFIRMED, $confirmed->status);
    }

    public function testRevertChangeset(): void
    {
        $toolCall = new ToolCall('create_job_offer', [
            'applicationId' => 'app-123',
        ]);

        $user = new User(id: 1, email: 'test@example.com');

        $changeset = $this->manager->stage($toolCall, ['result' => 'ok'], $user);

        $reverted = $this->manager->revert($changeset->id);

        $this->assertEquals(ChangesetStatus::REVERTED, $reverted->status);
    }

    public function testGetPendingReturnsPendingChangesets(): void
    {
        $user = new User(id: 1, email: 'test@example.com');

        $toolCall = new ToolCall('create_job_offer', [
            'applicationId' => 'app-123',
        ]);

        $this->manager->stage($toolCall, ['result' => 'ok'], $user);

        $pending = $this->manager->getPending('session_1');

        $this->assertNotEmpty($pending);
    }

    public function testConfirmThrowsForNonExistentChangeset(): void
    {
        $this->expectException(ChangesetNotFoundException::class);

        $user = new User(id: 1, email: 'test@example.com');
        $this->manager->confirm('non-existent-id', $user);
    }
}