<?php

namespace App\Tests\Service\Formation;

use App\Service\Formation\FormationService;
use App\Repository\Formation\FormationRepository;
use App\Repository\Formation\SessionFormationRepository;
use App\Repository\Formation\ParticipationFormationRepository;
use App\Repository\Formation\PresenceFormationRepository;
use App\Repository\Formation\SessionFeedbackRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class FormationServiceTest extends TestCase
{
    private FormationService $service;

    protected function setUp(): void
    {
        $this->service = new FormationService(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(FormationRepository::class),
            $this->createStub(SessionFormationRepository::class),
            $this->createStub(ParticipationFormationRepository::class),
            $this->createStub(PresenceFormationRepository::class),
            $this->createStub(SessionFeedbackRepository::class),
        );
    }

    public function testStatutPlanifieeQuandDateDebutDansLeFutur(): void
    {
        $debut = (new \DateTime())->modify('+5 days')->format('Y-m-d');
        $fin   = (new \DateTime())->modify('+10 days')->format('Y-m-d');

        $statut = $this->service->calculateStatut($debut, $fin);

        $this->assertSame('Planifiee', $statut);
    }

    public function testStatutEnCoursQuandAujourdhuiEntreDebutEtFin(): void
    {
        $debut = (new \DateTime())->modify('-2 days')->format('Y-m-d');
        $fin   = (new \DateTime())->modify('+2 days')->format('Y-m-d');

        $statut = $this->service->calculateStatut($debut, $fin);

        $this->assertSame('En cours', $statut);
    }

    public function testStatutTermineeQuandDateFinPassee(): void
    {
        $debut = (new \DateTime())->modify('-10 days')->format('Y-m-d');
        $fin   = (new \DateTime())->modify('-2 days')->format('Y-m-d');

        $statut = $this->service->calculateStatut($debut, $fin);

        $this->assertSame('Terminee', $statut);
    }

    public function testCreateSessionLanceExceptionSiDateFinAvantDateDebut(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->createSession([
            'id_formation'  => 1,
            'date_debut'    => '2025-06-10',
            'date_fin'      => '2025-06-05',
            'lieu'          => 'Tunis',
            'mode'          => 'Présentiel',
            'capacite_max'  => 20,
        ]);
    }
}