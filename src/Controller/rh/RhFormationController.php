<?php

namespace App\Controller\rh;

use App\Service\FormationService;
use App\Service\ParticipationService;
use App\Service\PresenceService;
use App\Service\SessionService;
use App\Form\Formation\FormationType;
use App\Form\Formation\SessionFormationType;
use App\Entity\Formation\Formation;
use App\Entity\Formation\SessionFormation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/rh/formation')]
final class RhFormationController extends AbstractController
{
    public function __construct(private readonly FormationService $formationService) {}

    #[Route('/', name: 'rh_formation_list', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $userId = $this->getUser()->getId();

        $search = $request->query->get('search', '');
        $type = $request->query->get('type', '');
        $sortQuery = $request->query->get('sort', 'created_at-DESC');

        $sortParts = explode('-', $sortQuery);
        $sort = $sortParts[0] ?? 'created_at';
        $dir = $sortParts[1] ?? 'DESC';

        return $this->render('DashboardHr/formation/formation_index.html.twig', [
            'formations' => $this->formationService->getFormationsByRhId($userId, $search, $type, $sort, $dir),
            'stats' => $this->formationService->getFormationStatsByRhId($userId),
            'filters' => [
                'search' => $search,
                'type' => $type,
                'sort' => $sortQuery,
            ],
        ]);
    }

    #[Route('/create', name: 'rh_formation_create', methods: ['GET', 'POST'])]
    public function create(Request $request, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        $formation = new Formation();
        $formation->setRhId($this->getUser()->getId());

        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($formation);
            $em->flush();

            $this->addFlash('success', 'Formation créée avec succès.');
            return $this->redirectToRoute('rh_formation_list');
        }

        return $this->render('DashboardHr/formation/formation_form.html.twig', [
            'formationForm' => $form,
            'isEdit' => false,
        ], new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200));
    }

    #[Route('/{id}/edit', name: 'rh_formation_edit', methods: ['GET', 'POST'])]
    public function edit(string $id, Request $request, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        $idInt = (int) $id;
        $formation = $this->formationService->getFormationById($idInt);
        if (!$formation) {
            throw $this->createNotFoundException('Formation non trouvée');
        }

        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Formation mise à jour avec succès.');
            return $this->redirectToRoute('rh_formation_list');
        }

        return $this->render('DashboardHr/formation/formation_form.html.twig', [
            'formationForm' => $form,
            'formation' => $formation,
            'isEdit' => true,
        ], new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200));
    }

    #[Route('/{id}/delete', name: 'rh_formation_delete', methods: ['POST'])]
    public function delete(string $id, Request $request): Response
    {
        $idInt = (int) $id;
        $formation = $this->formationService->getFormationById($idInt);
        if (!$formation) {
            throw $this->createNotFoundException('Formation non trouvée');
        }

        if ($this->isCsrfTokenValid('delete-formation-' . $idInt, $request->request->get('_token'))) {
            $this->formationService->deleteFormation($idInt);
            $this->addFlash('success', 'Formation supprimée avec succès.');
        }

        return $this->redirectToRoute('rh_formation_list');
    }

    #[Route('/{id}/sessions', name: 'rh_formation_sessions', methods: ['GET'])]
    public function sessions(string $id): Response
    {
        $idInt = (int) $id;
        $formation = $this->formationService->getFormationById($idInt);
        if (!$formation) {
            throw $this->createNotFoundException('Formation non trouvée');
        }

        return $this->render('DashboardHr/formation/formation_sessions.html.twig', [
            'formation' => $formation,
            'sessions' => $this->formationService->getSessionsByFormation($idInt),
        ]);
    }

    #[Route('/{id}/sessions/create', name: 'rh_formation_session_create', methods: ['GET', 'POST'])]
    public function createSession(string $id, Request $request, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        $idInt = (int) $id;
        $formation = $this->formationService->getFormationById($idInt);
        if (!$formation) {
            throw $this->createNotFoundException('Formation non trouvée');
        }

        $session = new SessionFormation();
        $session->setFormation($formation);

        $form = $this->createForm(SessionFormationType::class, $session);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (empty($session->getDateFin())) {
                $debutDate = clone $session->getDateDebut();
                $joursToAdd = max(0, $formation->getDuree() - 1);
                $debutDate->modify("+{$joursToAdd} days");
                $session->setDateFin($debutDate);
            }

            $now = new \DateTime(); $now->setTime(0,0,0);
            $debut = clone $session->getDateDebut(); $debut->setTime(0,0,0);
            $fin = clone $session->getDateFin(); $fin->setTime(0,0,0);

            if ($now < $debut) { $session->setStatut('Planifiee'); }
            elseif ($now > $fin) { $session->setStatut('Terminee'); }
            else { $session->setStatut('En cours'); }

            $em->persist($session);
            $em->flush();

            $this->addFlash('success', 'Session créée avec succès.');
            return $this->redirectToRoute('rh_formation_sessions', ['id' => $id]);
        }

        return $this->render('DashboardHr/formation/session_form.html.twig', [
            'formation' => $formation,
            'sessionForm' => $form,
            'isEdit' => false,
        ], new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200));
    }

    #[Route('/session/{id}/edit', name: 'rh_formation_session_edit', methods: ['GET', 'POST'])]
    public function editSession(string $id, Request $request, SessionService $sessionService, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        $idInt = (int) $id;
        $session = $sessionService->getSessionById($idInt);
        if (!$session) {
            throw $this->createNotFoundException('Session non trouvée');
        }
        $formation = $session->getFormation();

        $form = $this->createForm(SessionFormationType::class, $session);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$session->getDateFin()) {
                $debutDate = clone $session->getDateDebut();
                $joursToAdd = max(0, $formation->getDuree() - 1);
                $debutDate->modify("+{$joursToAdd} days");
                $session->setDateFin($debutDate);
            }

            $em->flush();

            $this->addFlash('success', 'Session modifiée avec succès.');
            return $this->redirectToRoute('rh_formation_sessions', ['id' => $formation->getId()]);
        }

        return $this->render('DashboardHr/formation/session_form.html.twig', [
            'formation' => $formation,
            'sessionForm' => $form,
            'session' => $session,
            'isEdit' => true,
        ], new Response(null, $form->isSubmitted() && !$form->isValid() ? 422 : 200));
    }

    #[Route('/session/{id}/participants', name: 'rh_formation_session_participants', methods: ['GET'])]
    public function participants(string $id, SessionService $sessionService, ParticipationService $participationService): Response
    {
        $idInt = (int) $id;
        $session = $sessionService->getSessionById($idInt);
        if (!$session) {
            throw $this->createNotFoundException('Session non trouvée');
        }

        return $this->render('DashboardHr/formation/formation_participants.html.twig', [
            'session' => $session,
            'formation' => $session->getFormation(),
            'participations' => $participationService->getSessionParticipations($idInt),
        ]);
    }

    #[Route('/participation/{id}/approve', name: 'rh_formation_participation_approve', methods: ['POST'])]
    public function approveParticipation(string $id, Request $request, ParticipationService $participationService): Response
    {
        $idInt = (int) $id;
        if ($this->isCsrfTokenValid('approve-participation-' . $idInt, (string) $request->request->get('_token'))) {
            $participationService->updateStatus($idInt, 'Accepte');
            $this->addFlash('success', 'Participation acceptée.');
        }
        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('rh_formation_list'));
    }

    #[Route('/participation/{id}/reject', name: 'rh_formation_participation_reject', methods: ['POST'])]
    public function rejectParticipation(string $id, Request $request, ParticipationService $participationService): Response
    {
        $idInt = (int) $id;
        if ($this->isCsrfTokenValid('reject-participation-' . $idInt, (string) $request->request->get('_token'))) {
            $participationService->updateStatus($idInt, 'Refuse');
            $this->addFlash('success', 'Participation refusée.');
        }
        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('rh_formation_list'));
    }

    #[Route('/session/{id}/presence', name: 'rh_formation_session_presence', methods: ['GET', 'POST'])]
    public function presence(string $id, Request $request, SessionService $sessionService, ParticipationService $participationService, PresenceService $presenceService): Response
    {
        $idInt = (int) $id;
        $session = $sessionService->getSessionById($idInt);
        if (!$session) {
            throw $this->createNotFoundException('Session non trouvée');
        }

        if ($session->getStatut() !== 'En cours') {
            $this->addFlash('error', 'Vous ne pouvez faire la présence que pour des sessions En cours.');
            return $this->redirectToRoute('rh_formation_sessions', ['id' => $session->getFormation()->getId()]);
        }

        $formation = $session->getFormation();

        $debut = $session->getDateDebut();
        $fin = $session->getDateFin();
        $today = new \DateTime();

        $maxDate = $today < $fin ? clone $today : clone $fin;

        $selectedDateStr = $request->query->get('date', $maxDate->format('Y-m-d'));
        $selectedDate = new \DateTime($selectedDateStr);

        if ($selectedDate < $debut || $selectedDate > $maxDate) {
            $selectedDate = clone $maxDate;
            $selectedDateStr = $maxDate->format('Y-m-d');
        }

        $allParticipations = $participationService->getSessionParticipations($id);
        $acceptedParticipations = array_filter(
            $allParticipations,
            fn($p) => $p->getStatutParticipation() === 'Accepte'
        );

        $dates = [];
        $interval = new \DateInterval('P1D');
        $endDateForPeriod = (clone $maxDate)->modify('+1 day');
        $period = new \DatePeriod($debut, $interval, $endDateForPeriod);
        foreach ($period as $dt) {
            $dates[] = $dt->format('Y-m-d');
        }

        if ($request->isMethod('POST')) {
            $presencesDataAll = $request->request->all('presences');
            if (!empty($presencesDataAll) && is_array($presencesDataAll)) {
                try {
                    foreach ($presencesDataAll as $date => $presencesData) {
                        $presenceService->savePresences($date, $presencesData);
                    }
                } catch (\Throwable) {
                    $this->addFlash('error', 'Erreur lors de l\'enregistrement des présences.');
                }

                $this->addFlash('success', 'Présences mises à jour avec succès.');
                return $this->redirectToRoute('rh_formation_sessions', ['id' => $formation->getId()]);
            }
        }

        $existingPresences = $presenceService->getPresencesBySession($id);
        $currentPresences = [];
        foreach ($existingPresences as $p) {
            $currentPresences[$p->getDatePresence()->format('Y-m-d')][$p->getParticipation()->getId()] = $p->getStatut();
        }

        return $this->render('DashboardHr/formation/formation_presence.html.twig', [
            'session' => $session,
            'formation' => $formation,
            'participations' => array_values($acceptedParticipations),
            'dates' => $dates,
            'currentPresences' => $currentPresences,
        ]);
    }
}
