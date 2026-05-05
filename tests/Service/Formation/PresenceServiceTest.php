<?php

namespace App\Tests\Service\Formation;

use App\Entity\Formation\ParticipationFormation;
use App\Entity\Formation\PresenceFormation;
use App\Repository\Formation\ParticipationFormationRepository;
use App\Repository\Formation\PresenceFormationRepository;
use App\Service\Formation\PresenceService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class PresenceServiceTest extends TestCase
{
    private PresenceFormationRepository $presenceRepository;
    private ParticipationFormationRepository $participationRepository;

    protected function setUp(): void
    {
        $this->presenceRepository      = $this->createStub(PresenceFormationRepository::class);
        $this->participationRepository = $this->createStub(ParticipationFormationRepository::class);
    }

    private function buildService(EntityManagerInterface $em): PresenceService
    {
        return new PresenceService($em, $this->presenceRepository, $this->participationRepository);
    }

    // ---------------------------------------------------------------
    // getAttendancePercentage — em passif → createStub()
    // ---------------------------------------------------------------

    public function testAttendancePercentageReturnsZeroWhenNoRecordedDays(): void
    {
        $this->presenceRepository->method('countByParticipation')->willReturn(0);

        $result = $this->buildService($this->createStub(EntityManagerInterface::class))
            ->getAttendancePercentage(1);

        $this->assertSame(0.0, $result);
    }

    public function testAttendancePercentageCalculatesCorrectly(): void
    {
        $this->presenceRepository->method('countByParticipation')->willReturn(10);
        $this->presenceRepository->method('countPresentByParticipation')->willReturn(8);

        $result = $this->buildService($this->createStub(EntityManagerInterface::class))
            ->getAttendancePercentage(1);

        $this->assertSame(80.0, $result);
    }

    public function testAttendancePercentageReturnsHundredWhenFullyPresent(): void
    {
        $this->presenceRepository->method('countByParticipation')->willReturn(5);
        $this->presenceRepository->method('countPresentByParticipation')->willReturn(5);

        $result = $this->buildService($this->createStub(EntityManagerInterface::class))
            ->getAttendancePercentage(1);

        $this->assertSame(100.0, $result);
    }

    // ---------------------------------------------------------------
    // savePresences — em actif → createMock() uniquement ici
    // ---------------------------------------------------------------

    public function testSavePresencesCreatesNewPresenceWhenNotExisting(): void
    {
        $this->presenceRepository->method('findOneByParticipationAndDate')->willReturn(null);
        $this->participationRepository->method('find')->willReturn(new ParticipationFormation());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('beginTransaction');
        $em->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(PresenceFormation::class));
        $em->expects($this->once())->method('flush');
        $em->expects($this->once())->method('commit');

        $this->buildService($em)->savePresences('2025-06-10', [42 => 'Present']);
    }

    public function testSavePresencesUpdatesExistingPresence(): void
    {
        $existing = new PresenceFormation();
        $existing->setStatut('Absent');

        $this->presenceRepository->method('findOneByParticipationAndDate')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('beginTransaction');
        $em->expects($this->never())->method('persist');
        $em->expects($this->once())->method('flush');
        $em->expects($this->once())->method('commit');

        $this->buildService($em)->savePresences('2025-06-10', [42 => 'Present']);

        $this->assertSame('Present', $existing->getStatut());
    }

    public function testSavePresencesSkipsWhenParticipationNotFound(): void
    {
        $this->presenceRepository->method('findOneByParticipationAndDate')->willReturn(null);
        $this->participationRepository->method('find')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('beginTransaction');
        $em->expects($this->never())->method('persist');
        $em->expects($this->once())->method('flush');
        $em->expects($this->once())->method('commit');

        $this->buildService($em)->savePresences('2025-06-10', [99 => 'Present']);
    }

    public function testSavePresencesRollsBackOnException(): void
    {
        $this->presenceRepository->method('findOneByParticipationAndDate')->willReturn(null);
        $this->participationRepository->method('find')->willReturn(new ParticipationFormation());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('beginTransaction');
        $em->method('flush')->willThrowException(new \RuntimeException('DB error'));
        $em->expects($this->once())->method('rollback');

        $this->expectException(\RuntimeException::class);

        $this->buildService($em)->savePresences('2025-06-10', [1 => 'Present']);
    }
}