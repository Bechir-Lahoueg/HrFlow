<?php

namespace App\Controller\rh;

use App\Security\DbUser;
use App\Service\FeedbackService;
use App\Service\FeedbackFormationService;
use App\Service\RequestService;
use App\Service\RequestTypeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/rh/relations', name: 'rh_relation_')]
class RhRelationController extends AbstractController
{
    public function __construct(
        private readonly RequestService         $requestService,
        private readonly RequestTypeService     $requestTypeService,
        private readonly FeedbackService        $feedbackService,
        private readonly FeedbackFormationService $ffService,
    ) {}

    // ═══════════════════════════════════════════════════════════════
    // DASHBOARD PRINCIPAL (onglets)
    // ═══════════════════════════════════════════════════════════════

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $user = $this->getDbUser();
        $rhId = $user->getId();

        $viewData = $this->buildIndexData($request, $rhId);

        return $this->render('DashboardHr/Relation/index.html.twig', $viewData);
    }

    // ═══════════════════════════════════════════════════════════════
    // DEMANDES — APPROVE / REJECT
    // ═══════════════════════════════════════════════════════════════

    #[Route('/requests/{id}/approve', name: 'request_approve', methods: ['POST'])]
    public function approveRequest(int $id, Request $request): Response
    {
        $user = $this->getDbUser();
        $comment = trim((string) $request->request->get('comment', ''));
        $this->requestService->updateStatus($id, 'approved', $user->getId(), $comment !== '' ? $comment : null);
        $this->addFlash('success', 'Demande approuvée avec succès.');
        return $this->redirectToRoute('rh_relation_index', ['tab' => 'requests']);
    }

    #[Route('/requests/{id}/reject', name: 'request_reject', methods: ['POST'])]
    public function rejectRequest(int $id, Request $request): Response
    {
        $user = $this->getDbUser();
        $reason = trim((string) $request->request->get('reason', ''));
        $this->requestService->updateStatus($id, 'rejected', $user->getId(), $reason !== '' ? $reason : null);
        $this->addFlash('success', 'Demande rejetée.');
        return $this->redirectToRoute('rh_relation_index', ['tab' => 'requests']);
    }

    // ═══════════════════════════════════════════════════════════════
    // TYPES DE DEMANDES — CRUD
    // ═══════════════════════════════════════════════════════════════

    #[Route('/request-types/new', name: 'request_type_new', methods: ['POST'])]
    public function newRequestType(Request $request): Response
    {
        $data = $request->request->all();
        $data['requires_approval'] = $request->request->has('requires_approval');

        $errors = $this->requestTypeService->validate($data);
        if (count($errors) > 0) {
            $viewData = $this->buildIndexData($request, $this->getDbUser()->getId());
            $viewData['typeErrors'] = $errors;
            $viewData['typeOld'] = $data;
            $viewData['typeMode'] = 'new';
            return $this->render('DashboardHr/Relation/index.html.twig', $viewData, new Response(null, 422));
        }

        $this->requestTypeService->add($data);
        $this->addFlash('success', 'Type de demande créé.');
        return $this->redirectToRoute('rh_relation_index', ['tab' => 'config']);
    }

    #[Route('/request-types/{id}/edit', name: 'request_type_edit', methods: ['POST'])]
    public function editRequestType(int $id, Request $request): Response
    {
        $data = $request->request->all();
        $data['requires_approval'] = $request->request->has('requires_approval');

        $errors = $this->requestTypeService->validate($data);
        if (count($errors) > 0) {
            $viewData = $this->buildIndexData($request, $this->getDbUser()->getId());
            $viewData['typeErrors'] = $errors;
            $viewData['typeOld'] = $data;
            $viewData['typeMode'] = 'edit';
            $viewData['typeEditId'] = $id;
            return $this->render('DashboardHr/Relation/index.html.twig', $viewData, new Response(null, 422));
        }

        $this->requestTypeService->update($id, $data);
        $this->addFlash('success', 'Type de demande modifié.');
        return $this->redirectToRoute('rh_relation_index', ['tab' => 'config']);
    }

    #[Route('/request-types/{id}/delete', name: 'request_type_delete', methods: ['POST'])]
    public function deleteRequestType(int $id): Response
    {
        $this->requestTypeService->delete($id);
        $this->addFlash('success', 'Type de demande supprimé.');
        return $this->redirectToRoute('rh_relation_index', ['tab' => 'config']);
    }

    // ═══════════════════════════════════════════════════════════════
    // FEEDBACKS — ACKNOWLEDGE
    // ═══════════════════════════════════════════════════════════════

    #[Route('/feedbacks/{id}/acknowledge', name: 'feedback_acknowledge', methods: ['POST'])]
    public function acknowledgeFeedback(int $id): Response
    {
        $this->feedbackService->acknowledge($id);
        $this->addFlash('success', 'Feedback marqué comme traité.');
        return $this->redirectToRoute('rh_relation_index', ['tab' => 'feedbacks']);
    }

    // ═══════════════════════════════════════════════════════════════
    // AJAX : détails d'une demande (modal)
    // ═══════════════════════════════════════════════════════════════

    #[Route('/requests/{id}/details', name: 'request_details', methods: ['GET'])]
    public function requestDetails(int $id): JsonResponse
    {
        $req = $this->requestService->getById($id);
        if (!$req) return new JsonResponse(['error' => 'Not found'], 404);
        return new JsonResponse($req);
    }

    #[Route('/feedbacks/{id}/details', name: 'feedback_details', methods: ['GET'])]
    public function feedbackDetails(int $id): JsonResponse
    {
        $fb = $this->feedbackService->getById($id);
        if (!$fb) return new JsonResponse(['error' => 'Not found'], 404);
        if ($fb['is_anonymous']) $fb['from_username'] = 'Anonyme';
        $fb['rating_stars'] = $this->feedbackService->getRatingStars((int)$fb['rating']);
        return new JsonResponse($fb);
    }

    #[Route('/ff/{id}/details', name: 'ff_details', methods: ['GET'])]
    public function ffDetails(int $id): JsonResponse
    {
        $ff = $this->ffService->getById($id);
        if (!$ff) return new JsonResponse(['error' => 'Not found'], 404);
        $ff['rating_stars'] = $this->ffService->getRatingStars((int)$ff['rating']);
        return new JsonResponse($ff);
    }

    /** @return array<string, mixed> */
    private function buildIndexData(Request $request, int $rhId): array
    {
        $tab    = $request->query->get('tab', 'requests');

        $requests     = $this->requestService->getByRhId($rhId);
        $requestTypes = $this->requestTypeService->getAll();

        $statusFilter = trim((string) $request->query->get('status', ''));
        $searchReq    = trim((string) $request->query->get('search_req', ''));
        if ($statusFilter !== '') {
            $requests = array_filter($requests, fn($r) => $r['status'] === $statusFilter);
        }
        if ($searchReq !== '') {
            $requests = array_filter($requests, fn($r) =>
                stripos($r['title'], $searchReq) !== false ||
                stripos($r['employee_name'] ?? '', $searchReq) !== false
            );
        }

        $allReqs = $this->requestService->getByRhId($rhId);
        $requestStats = [
            'total'    => count($allReqs),
            'pending'  => count(array_filter($allReqs, fn($r) => $r['status'] === 'pending')),
            'approved' => count(array_filter($allReqs, fn($r) => $r['status'] === 'approved')),
            'rejected' => count(array_filter($allReqs, fn($r) => $r['status'] === 'rejected')),
        ];

        $feedbacks    = $this->feedbackService->getByRhId($rhId);
        $typeFilter   = trim((string) $request->query->get('feedback_type', ''));
        $searchFb     = trim((string) $request->query->get('search_fb', ''));
        if ($typeFilter !== '') {
            $feedbacks = array_filter($feedbacks, fn($f) => $f['feedback_type'] === $typeFilter);
        }
        if ($searchFb !== '') {
            $feedbacks = array_filter($feedbacks, fn($f) =>
                stripos($f['from_username'] ?? '', $searchFb) !== false ||
                stripos($f['to_username'] ?? '', $searchFb) !== false
            );
        }
        $allFb = $this->feedbackService->getByRhId($rhId);
        $avgRating = count($allFb) > 0
            ? round(array_sum(array_column($allFb, 'rating')) / count($allFb), 1)
            : 0;

        $ffList         = $this->ffService->getByRhId($rhId);
        $ffFormFilter   = $request->query->get('ff_formation', '');
        if ($ffFormFilter) {
            $ffList = array_filter($ffList, fn($f) => ($f['formation_name'] ?? '') === $ffFormFilter);
        }
        $ffFormations = array_unique(array_column($this->ffService->getByRhId($rhId), 'formation_name'));
        $ffAvg = count($ffList) > 0
            ? round(array_sum(array_column(array_values($ffList), 'rating')) / count($ffList), 1)
            : 0;
        $ffRecRate = count($ffList) > 0
            ? round(count(array_filter(array_values($ffList), fn($f) => $f['recommande'])) / count($ffList) * 100)
            : 0;

        return [
            'tab'            => $tab,
            'requests'       => array_values($requests),
            'requestStats'   => $requestStats,
            'requestTypes'   => $requestTypes,
            'statusFilter'   => $statusFilter,
            'searchReq'      => $searchReq,
            'feedbacks'      => array_values($feedbacks),
            'avgRating'      => $avgRating,
            'totalFeedbacks' => count($allFb),
            'typeFilter'     => $typeFilter,
            'searchFb'       => $searchFb,
            'ffList'         => array_values($ffList),
            'ffFormations'   => $ffFormations,
            'ffFormFilter'   => $ffFormFilter,
            'ffAvg'          => $ffAvg,
            'ffRecRate'      => $ffRecRate,
            'feedbackTypes'  => ['performance', 'behavior', 'collaboration', 'other'],
            'typeErrors'     => [],
            'typeOld'        => [],
            'typeMode'       => null,
            'typeEditId'     => null,
        ];
    }

    private function getDbUser(): DbUser
    {
        $user = $this->getUser();
        if (!$user instanceof DbUser) {
            throw $this->createAccessDeniedException('Utilisateur non authentifie.');
        }

        return $user;
    }
}
