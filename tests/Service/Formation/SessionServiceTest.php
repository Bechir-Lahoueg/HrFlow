<?php

namespace App\Tests\Service\Formation;

use App\Entity\Formation\Formation;
use App\Entity\Formation\SessionFormation;
use App\Repository\Formation\SessionFormationRepository;
use App\Service\Formation\SessionService;
use PHPUnit\Framework\TestCase;

class SessionServiceTest extends TestCase
{
    private SessionFormationRepository $sessionRepository;
    private SessionService $service;

    protected function setUp(): void
    {
        $this->sessionRepository = $this->createStub(SessionFormationRepository::class);

        $this->service = new SessionService($this->sessionRepository);
    }

    // ---------------------------------------------------------------
    // getSessionById
    // ---------------------------------------------------------------

    public function testGetSessionByIdReturnsSessionWhenFound(): void
    {
        $session = new SessionFormation();

        $this->sessionRepository
            ->method('find')
            ->willReturn($session);

        $result = $this->service->getSessionById(1);

        $this->assertSame($session, $result);
    }

    public function testGetSessionByIdReturnsNullWhenNotFound(): void
    {
        $this->sessionRepository
            ->method('find')
            ->willReturn(null);

        $result = $this->service->getSessionById(999);

        $this->assertNull($result);
    }

    // ---------------------------------------------------------------
    // getIdFormationBySessionId
    // ---------------------------------------------------------------

    public function testGetIdFormationBySessionIdReturnsFormationId(): void
    {
        $formation = new Formation();
        // On force l'id via réflexion car pas de setId()
        $ref = new \ReflectionProperty(Formation::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($formation, 42);

        $session = (new SessionFormation())->setFormation($formation);

        $this->sessionRepository
            ->method('find')
            ->willReturn($session);

        $result = $this->service->getIdFormationBySessionId(1);

        $this->assertSame(42, $result);
    }

    public function testGetIdFormationBySessionIdReturnsZeroWhenSessionNotFound(): void
    {
        $this->sessionRepository
            ->method('find')
            ->willReturn(null);

        $result = $this->service->getIdFormationBySessionId(999);

        $this->assertSame(0, $result);
    }

    public function testGetIdFormationBySessionIdReturnsZeroWhenNoFormation(): void
    {
        $session = new SessionFormation(); // pas de formation attachée

        $this->sessionRepository
            ->method('find')
            ->willReturn($session);

        $result = $this->service->getIdFormationBySessionId(1);

        $this->assertSame(0, $result);
    }

    // ---------------------------------------------------------------
    // getSessionsByFormation
    // ---------------------------------------------------------------

    public function testGetSessionsByFormationReturnsArray(): void
    {
        $sessions = [new SessionFormation(), new SessionFormation()];

        $this->sessionRepository
            ->method('findByFormation')
            ->willReturn($sessions);

        $result = $this->service->getSessionsByFormation(1);

        $this->assertCount(2, $result);
        $this->assertSame($sessions, $result);
    }

    public function testGetSessionsByFormationReturnsEmptyArrayWhenNone(): void
    {
        $this->sessionRepository
            ->method('findByFormation')
            ->willReturn([]);

        $result = $this->service->getSessionsByFormation(1);

        $this->assertSame([], $result);
    }

    // ---------------------------------------------------------------
    // getAvailableSessions
    // ---------------------------------------------------------------

    public function testGetAvailableSessionsReturnsArray(): void
    {
        $sessions = [new SessionFormation()];

        $this->sessionRepository
            ->method('findAvailable')
            ->willReturn($sessions);

        $result = $this->service->getAvailableSessions();

        $this->assertCount(1, $result);
        $this->assertSame($sessions, $result);
    }

    // ---------------------------------------------------------------
    // syncStatuses
    // ---------------------------------------------------------------

    public function testSyncStatusesCallsAutoUpdateStatuses(): void
    {
        $repo = $this->createMock(SessionFormationRepository::class);
        $repo->expects($this->once())->method('autoUpdateStatuses');

        $service = new SessionService($repo);
        $service->syncStatuses();
    }
}