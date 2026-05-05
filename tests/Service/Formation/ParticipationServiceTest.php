<?php

namespace App\Tests\Service\Formation;

use App\Entity\Formation\Formation;
use App\Entity\Formation\ParticipationFormation;
use App\Entity\Formation\SessionFormation;
use App\Entity\Rh\Employee;
use App\Repository\Formation\ParticipationFormationRepository;
use App\Repository\Formation\PresenceFormationRepository;
use App\Repository\Formation\SessionFormationRepository;
use App\Repository\Rh\EmployeeRepository;
use App\Repository\Rh\LeaveRequestRepository;
use App\Repository\Rh\UserRepository;
use App\Service\Formation\ParticipationService;
use App\Service\Formation\PresenceService;
use App\Service\Shared\HrFlowMailer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;

class ParticipationServiceTest extends TestCase
{
    private ParticipationFormationRepository $participationRepository;
    private SessionFormationRepository $sessionRepository;
    private EmployeeRepository $employeeRepository;
    private LeaveRequestRepository $leaveRequestRepository;

    protected function setUp(): void
    {
        $this->participationRepository = $this->createStub(ParticipationFormationRepository::class);
        $this->sessionRepository       = $this->createStub(SessionFormationRepository::class);
        $this->employeeRepository      = $this->createStub(EmployeeRepository::class);
        $this->leaveRequestRepository  = $this->createStub(LeaveRequestRepository::class);
    }

    // Helper pour construire le service avec un em donné
    private function buildService(EntityManagerInterface $em): ParticipationService
    {
        $presenceService = new PresenceService(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(PresenceFormationRepository::class),
            $this->createStub(ParticipationFormationRepository::class),
        );

        $hrFlowMailer = new HrFlowMailer(
            $this->createStub(MailerInterface::class),
            $this->createStub(Environment::class),
            $this->createStub(UserRepository::class),
            'no-reply@example.com',
        );

        return new ParticipationService(
            $em,
            $this->participationRepository,
            $this->sessionRepository,
            $this->employeeRepository,
            $this->leaveRequestRepository,
            $presenceService,
            $hrFlowMailer,
        );
    }

    // ---------------------------------------------------------------
    // Test 1 : session ou employee introuvable → échec
    // ---------------------------------------------------------------

    public function testRegisterFailsWhenSessionOrEmployeeMissing(): void
    {
        $this->participationRepository->method('findByEmployeeAndSession')->willReturn(null);
        $this->employeeRepository->method('find')->willReturn(null);
        $this->sessionRepository->method('find')->willReturn(null);

        $service = $this->buildService($this->createStub(EntityManagerInterface::class));
        $result  = $service->registerEmployeeDetailed(10, 20);

        $this->assertFalse($result['ok']);
        $this->assertSame(['Session introuvable.'], $result['reasons']);
    }

    // ---------------------------------------------------------------
    // Test 2 : déjà inscrit → échec avec raison
    // ---------------------------------------------------------------

    public function testRegisterFailsWhenAlreadyRegistered(): void
    {
        $formation = (new Formation())
            ->setTitre('Test')->setType('Technique')
            ->setDuree(3)->setOrganisme('Org')->setRhId(1);

        $session = (new SessionFormation())->setFormation($formation);

        $employee = (new Employee())
            ->setFirstName('Alice')->setLastName('Doe')
            ->setAge(25)->setJobTitle('Dev')
            ->setEmail('alice@example.com')
            ->setPassword('secret')->setRhId(1);

        $this->participationRepository->method('findByEmployeeAndSession')->willReturn(new ParticipationFormation());
        $this->employeeRepository->method('find')->willReturn($employee);
        $this->sessionRepository->method('find')->willReturn($session);
        $this->participationRepository->method('hasAcceptedInFormation')->willReturn(false);
        $this->leaveRequestRepository->method('hasAcceptedDateOverlap')->willReturn(false);

        $service = $this->buildService($this->createStub(EntityManagerInterface::class));
        $result  = $service->registerEmployeeDetailed(10, 20);

        $this->assertFalse($result['ok']);
        $this->assertContains('Vous etes deja inscrit a une session de cette formation', $result['reasons']);
    }

    // ---------------------------------------------------------------
    // Test 3 : chevauchement congé → échec avec raison
    // ---------------------------------------------------------------

    public function testRegisterFailsWhenLeaveOverlap(): void
    {
        $formation = (new Formation())
            ->setTitre('Test')->setType('Technique')
            ->setDuree(3)->setOrganisme('Org')->setRhId(1);

        $session = (new SessionFormation())
            ->setFormation($formation)
            ->setDateDebut(new \DateTime('2025-07-01'))
            ->setDateFin(new \DateTime('2025-07-10'));

        $employee = (new Employee())
            ->setFirstName('Bob')->setLastName('Smith')
            ->setAge(30)->setJobTitle('Dev')
            ->setEmail('bob@example.com')
            ->setPassword('secret')->setRhId(1);

        $this->participationRepository->method('findByEmployeeAndSession')->willReturn(null);
        $this->employeeRepository->method('find')->willReturn($employee);
        $this->sessionRepository->method('find')->willReturn($session);
        $this->participationRepository->method('hasAcceptedInFormation')->willReturn(false);
        $this->leaveRequestRepository->method('hasAcceptedDateOverlap')->willReturn(true);

        $service = $this->buildService($this->createStub(EntityManagerInterface::class));
        $result  = $service->registerEmployeeDetailed(10, 20);

        $this->assertFalse($result['ok']);
        $this->assertContains('Cette session chevauche avec une periode de conge validee', $result['reasons']);
    }

    // ---------------------------------------------------------------
    // Test 4 : tout valide → persist + flush appelés, ok = true
    // ---------------------------------------------------------------

    public function testRegisterCreatesParticipationSuccessfully(): void
    {
        $formation = (new Formation())
            ->setTitre('Test')->setType('Technique')
            ->setDuree(3)->setOrganisme('Org')->setRhId(1);

        $session = (new SessionFormation())->setFormation($formation);

        $employee = (new Employee())
            ->setFirstName('Alice')->setLastName('Doe')
            ->setAge(25)->setJobTitle('Dev')
            ->setEmail('alice@example.com')
            ->setPassword('secret')->setRhId(1);

        $this->participationRepository->method('findByEmployeeAndSession')->willReturn(null);
        $this->employeeRepository->method('find')->willReturn($employee);
        $this->sessionRepository->method('find')->willReturn($session);
        $this->participationRepository->method('hasAcceptedInFormation')->willReturn(false);
        $this->leaveRequestRepository->method('hasAcceptedDateOverlap')->willReturn(false);

        // createMock() uniquement ici car on vérifie des expects()
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(ParticipationFormation::class));
        $em->expects($this->once())->method('flush');

        $service = $this->buildService($em);
        $result  = $service->registerEmployeeDetailed(10, 20);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['reasons']);
    }
}