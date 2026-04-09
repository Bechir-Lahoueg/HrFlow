<?php

namespace App\Controller\rh;

use App\Service\FormationService;
use App\Service\ParticipationService;
use App\Service\SessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/rh/formation')]
final class RhFormationController extends AbstractController
{
    public function __construct(private readonly FormationService $formationService) {}

    #[Route('/', name: 'rh_formation_list', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('DashboardHr/formation/formation_index.html.twig', [
            'formations' => $this->formationService->getAllFormations(),
            'stats' => $this->formationService->getFormationStats(),
        ]);
    }

    #[Route('/create', name: 'rh_formation_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $data = [
                'titre' => $request->request->get('titre'),
                'description' => $request->request->get('description'),
                'type' => $request->request->get('type'),
                'duree' => (int) $request->request->get('duree'),
                'organisme' => $request->request->get('organisme'),
                'objectifs' => $request->request->get('objectifs'),
                'id_rh' => $this->getUser()->getId(),
            ];

            $this->formationService->createFormation($data);
            $this->addFlash('success', 'Formation créée avec succès.');

            return $this->redirectToRoute('rh_formation_list');
        }

        return $this->render('DashboardHr/formation/formation_form.html.twig', [
            'formation' => null,
            'isEdit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'rh_formation_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $formation = $this->formationService->getFormationById($id);
        if (!$formation) {
            throw $this->createNotFoundException('Formation non trouvée');
        }

        if ($request->isMethod('POST')) {
            $data = [
                'titre' => $request->request->get('titre'),
                'description' => $request->request->get('description'),
                'type' => $request->request->get('type'),
                'duree' => (int) $request->request->get('duree'),
                'organisme' => $request->request->get('organisme'),
                'objectifs' => $request->request->get('objectifs'),
            ];

            $this->formationService->updateFormation($id, $data);
            $this->addFlash('success', 'Formation mise à jour avec succès.');

            return $this->redirectToRoute('rh_formation_list');
        }

        return $this->render('DashboardHr/formation/formation_form.html.twig', [
            'formation' => $formation,
            'isEdit' => true,
        ]);
    }

    #[Route('/{id}/delete', name: 'rh_formation_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $formation = $this->formationService->getFormationById($id);
        if (!$formation) {
            throw $this->createNotFoundException('Formation non trouvée');
        }

        if ($this->isCsrfTokenValid('delete-formation-' . $id, $request->request->get('_token'))) {
            $this->formationService->deleteFormation($id);
            $this->addFlash('success', 'Formation supprimée avec succès.');
        }

        return $this->redirectToRoute('rh_formation_list');
    }

    #[Route('/{id}/sessions', name: 'rh_formation_sessions', methods: ['GET'])]
    public function sessions(int $id): Response
    {
        $formation = $this->formationService->getFormationById($id);
        if (!$formation) {
            throw $this->createNotFoundException('Formation non trouvée');
        }

        $sessions = $this->formationService->getSessionsByFormation($id);

        return $this->render('DashboardHr/formation/formation_sessions.html.twig', [
            'formation' => $formation,
            'sessions' => $sessions,
        ]);
    }

    #[Route('/{id}/sessions/create', name: 'rh_formation_session_create', methods: ['GET', 'POST'])]
    public function createSession(int $id, Request $request): Response
    {
        $formation = $this->formationService->getFormationById($id);
        if (!$formation) {
            throw $this->createNotFoundException('Formation non trouvée');
        }

        if ($request->isMethod('POST')) {
            $data = [
                'id_formation' => $id,
                'date_debut' => $request->request->get('date_debut'),
                'date_fin' => $request->request->get('date_fin'),
                'lieu' => $request->request->get('lieu'),
                'mode' => $request->request->get('mode'),
                'capacite_max' => (int) $request->request->get('capacite_max'),
                'statut' => $request->request->get('statut'),
            ];

            if (empty($data['date_fin'])) {
                $debutDate = new \DateTime($data['date_debut']);
                $duree = (int) $formation['duree'];
                $joursToAdd = max(0, $duree - 1);
                $debutDate->modify("+{$joursToAdd} days");
                $data['date_fin'] = $debutDate->format('Y-m-d');
            }

            $this->formationService->createSession($data);
            $this->addFlash('success', 'Session créée avec succès.');

            return $this->redirectToRoute('rh_formation_sessions', ['id' => $id]);
        }

        return $this->render('DashboardHr/formation/session_form.html.twig', [
            'formation' => $formation,
            'session' => null,
            'isEdit' => false,
        ]);
    }

    #[Route('/session/{id}/edit', name: 'rh_formation_session_edit', methods: ['GET', 'POST'])]
    public function editSession(int $id, Request $request, SessionService $sessionService): Response
    {
        $session = $sessionService->getSessionById($id);
        if (!$session) {
            throw $this->createNotFoundException('Session non trouvée');
        }

        $formation = $this->formationService->getFormationById($session['id_formation']);

        if ($request->isMethod('POST')) {
            $data = [
                'date_debut' => $request->request->get('date_debut'),
                'date_fin' => $request->request->get('date_fin'),
                'lieu' => $request->request->get('lieu'),
                'mode' => $request->request->get('mode'),
                'capacite_max' => (int) $request->request->get('capacite_max'),
                'statut' => $request->request->get('statut'),
            ];

            if (empty($data['date_fin'])) {
                $debutDate = new \DateTime($data['date_debut']);
                $duree = (int) $formation['duree'];
                $joursToAdd = max(0, $duree - 1);
                $debutDate->modify("+{$joursToAdd} days");
                $data['date_fin'] = $debutDate->format('Y-m-d');
            }

            $this->formationService->updateSession($id, $data);
            $this->addFlash('success', 'Session modifiée avec succès.');

            return $this->redirectToRoute('rh_formation_sessions', ['id' => $session['id_formation']]);
        }

        return $this->render('DashboardHr/formation/session_form.html.twig', [
            'formation' => $formation,
            'session' => $session,
            'isEdit' => true,
        ]);
    }

    #[Route('/session/{id}/participants', name: 'rh_formation_session_participants', methods: ['GET'])]
    public function participants(int $id, SessionService $sessionService, ParticipationService $participationService): Response
    {
        $session = $sessionService->getSessionById($id);
        if (!$session) {
            throw $this->createNotFoundException('Session non trouvée');
        }

        $formation = $this->formationService->getFormationById($session['id_formation']);
        $participations = $participationService->getSessionParticipations($id);

        return $this->render('DashboardHr/formation/formation_participants.html.twig', [
            'session' => $session,
            'formation' => $formation,
            'participations' => $participations,
        ]);
    }

    #[Route('/participation/{id}/approve', name: 'rh_formation_participation_approve', methods: ['POST'])]
    public function approveParticipation(int $id, Request $request, ParticipationService $participationService): Response
    {
        if ($this->isCsrfTokenValid('approve-participation-' . $id, (string) $request->request->get('_token'))) {
            $participationService->updateStatus($id, 'Accepte');
            $this->addFlash('success', 'Participation acceptée.');
        }
        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('rh_formation_list'));
    }

    #[Route('/participation/{id}/reject', name: 'rh_formation_participation_reject', methods: ['POST'])]
    public function rejectParticipation(int $id, Request $request, ParticipationService $participationService): Response
    {
        if ($this->isCsrfTokenValid('reject-participation-' . $id, (string) $request->request->get('_token'))) {
            $participationService->updateStatus($id, 'Refuse');
            $this->addFlash('success', 'Participation refusée.');
        }
        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('rh_formation_list'));
    }
}

