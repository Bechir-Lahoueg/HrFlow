<?php

namespace App\Controller\employee;

use App\Service\FeedbackService;
use App\Service\FeedbackFormationService;
use App\Service\RequestService;
use App\Service\RequestTypeService;
use App\Service\Shared\HuggingFaceEmotionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/employee/relations', name: 'employee_relation_')]
class EmployeeRelationController extends AbstractController
{
    public function __construct(
        private readonly RequestService            $requestService,
        private readonly RequestTypeService        $requestTypeService,
        private readonly FeedbackService           $feedbackService,
        private readonly FeedbackFormationService  $ffService,
        private readonly HuggingFaceEmotionService $emotionService,
    ) {}

    // ═══════════════════════════════════════════════════════════════
    // DEMANDES
    // ═══════════════════════════════════════════════════════════════

    #[Route('/requests', name: 'requests')]
    public function requests(Request $request): Response
    {
        $user       = $this->getUser();
        $employeeId = $user->getId();

        $viewData = $this->buildRequestsViewData($request, $employeeId);

        return $this->render('DashboardEmployee/Relation/requests.html.twig', $viewData);
    }

    #[Route('/requests/new', name: 'request_new', methods: ['POST'])]
    public function newRequest(Request $request): Response
    {
        $user = $this->getUser();
        $data = $request->request->all();
        $data['user_id'] = $user->getId();

        $errors = $this->requestService->validateCreate($data);

        $file = $this->extractAttachmentFile($request);
        if ($file instanceof UploadedFile) {
            $validation = $this->validateRequestAttachment($file);
            if (!$validation['success']) {
                $errors['attachment'] = (string) $validation['message'];
            }
        } else {
            $rawUploadError = $this->extractAttachmentUploadError();
            if ($rawUploadError !== null && $rawUploadError !== UPLOAD_ERR_NO_FILE) {
                $errors['attachment'] = $this->mapUploadError($rawUploadError);
            }
        }

        if (count($errors) > 0) {
            $viewData = $this->buildRequestsViewData($request, $user->getId());
            $viewData['requestErrors'] = $errors;
            $viewData['requestOld'] = $data;
            return $this->render('DashboardEmployee/Relation/requests.html.twig', $viewData, new Response(null, 422));
        }

        if ($file instanceof UploadedFile) {
            $upload = $this->storeRequestAttachment($file);
            if (!$upload['success']) {
                $viewData = $this->buildRequestsViewData($request, $user->getId());
                $viewData['requestErrors'] = ['attachment' => (string) $upload['message']];
                $viewData['requestOld'] = $data;
                return $this->render('DashboardEmployee/Relation/requests.html.twig', $viewData, new Response(null, 422));
            }
            $data['attachment_url'] = (string) $upload['path'];
        }

        if (!$this->requestService->add($data)) {
            $viewData = $this->buildRequestsViewData($request, $user->getId());
            $viewData['requestErrors'] = ['general' => 'Impossible d\'enregistrer la demande. Réessayez.'];
            $viewData['requestOld'] = $data;
            return $this->render('DashboardEmployee/Relation/requests.html.twig', $viewData, new Response(null, 422));
        }

        $this->addFlash('success', 'Votre demande a été soumise avec succès !');
        return $this->redirectToRoute('employee_relation_requests');
    }

    #[Route('/requests/{id}/edit', name: 'request_edit', methods: ['POST'])]
    public function editRequest(int $id, Request $request): Response
    {
        $req = $this->requestService->getById($id);
        if ($req && $req['status'] === 'pending') {
            $data = $request->request->all();
            $errors = $this->requestService->validateUpdate($data);
            if (count($errors) > 0) {
                $viewData = $this->buildRequestsViewData($request, $this->getUser()->getId());
                $viewData['requestEditErrors'] = $errors;
                $viewData['requestEditOld'] = $data;
                $viewData['requestEditId'] = $id;
                return $this->render('DashboardEmployee/Relation/requests.html.twig', $viewData, new Response(null, 422));
            }
            $this->requestService->update($id, $data);
            $this->addFlash('success', 'Demande mise à jour.');
        }
        return $this->redirectToRoute('employee_relation_requests');
    }

    #[Route('/requests/{id}/cancel', name: 'request_cancel', methods: ['POST'])]
    public function cancelRequest(int $id): Response
    {
        $user = $this->getUser();
        $req  = $this->requestService->getById($id);
        if ($req && $req['status'] === 'pending') {
            $this->requestService->updateStatus($id, 'cancelled', $user->getId(), 'Annulée par l\'employé');
            $this->addFlash('success', 'Demande annulée.');
        }
        return $this->redirectToRoute('employee_relation_requests');
    }

    #[Route('/requests/{id}/delete', name: 'request_delete', methods: ['POST'])]
    public function deleteRequest(int $id): Response
    {
        $req = $this->requestService->getById($id);
        if ($req && $req['status'] === 'pending') {
            $this->requestService->delete($id);
            $this->addFlash('success', 'Demande supprimée.');
        }
        return $this->redirectToRoute('employee_relation_requests');
    }

    // ═══════════════════════════════════════════════════════════════
    // FEEDBACKS EMPLOYÉS
    // ═══════════════════════════════════════════════════════════════

    #[Route('/feedbacks', name: 'feedbacks')]
    public function feedbacks(Request $request): Response
    {
        $user       = $this->getUser();
        $employeeId = $user->getId();

        $viewData = $this->buildFeedbacksViewData($request, $employeeId);

        return $this->render('DashboardEmployee/Relation/feedbacks.html.twig', $viewData);
    }

    #[Route('/feedbacks/send', name: 'feedback_send', methods: ['POST'])]
    public function sendFeedback(Request $request): Response
    {
        $user = $this->getUser();
        $data = $request->request->all();
        $data['from_user_id'] = $user->getId();
        $data['is_anonymous'] = $request->request->has('is_anonymous');

        $errors = $this->feedbackService->validateCreate($data);
        if (count($errors) > 0) {
            $viewData = $this->buildFeedbacksViewData($request, $user->getId());
            $viewData['tab'] = 'employee';
            $viewData['fbErrors'] = $errors;
            $viewData['fbOld'] = $data;
            return $this->render('DashboardEmployee/Relation/feedbacks.html.twig', $viewData, new Response(null, 422));
        }

        $emotion = $this->emotionService->analyze((string) ($data['comment'] ?? ''));
        $data['emotion_label'] = $emotion['label'];
        $data['emotion_score'] = $emotion['score'];

        $this->feedbackService->add($data);
        $this->addFlash('success', 'Feedback envoyé avec succès !');
        return $this->redirectToRoute('employee_relation_feedbacks');
    }

    #[Route('/feedbacks/{id}/edit', name: 'feedback_edit', methods: ['POST'])]
    public function editFeedback(int $id, Request $request): Response
    {
        $user = $this->getUser();
        $fb   = $this->feedbackService->getById($id);
        if ($fb && (int)$fb['from_user_id'] === $user->getId()) {
            $data = $request->request->all();
            $data['is_anonymous'] = $request->request->has('is_anonymous');
            $errors = $this->feedbackService->validateUpdate($data);
            if (count($errors) > 0) {
                $viewData = $this->buildFeedbacksViewData($request, $user->getId());
                $viewData['tab'] = 'employee';
                $viewData['fbEditErrors'] = $errors;
                $viewData['fbEditOld'] = $data;
                $viewData['fbEditId'] = $id;
                return $this->render('DashboardEmployee/Relation/feedbacks.html.twig', $viewData, new Response(null, 422));
            }

            $emotion = $this->emotionService->analyze((string) ($data['comment'] ?? ''));
            $data['emotion_label'] = $emotion['label'];
            $data['emotion_score'] = $emotion['score'];

            $this->feedbackService->update($id, $data);
            $this->addFlash('success', 'Feedback modifié.');
        }
        return $this->redirectToRoute('employee_relation_feedbacks');
    }

    #[Route('/feedbacks/{id}/delete', name: 'feedback_delete', methods: ['POST'])]
    public function deleteFeedback(int $id): Response
    {
        $user = $this->getUser();
        $fb   = $this->feedbackService->getById($id);
        if ($fb && (int)$fb['from_user_id'] === $user->getId()) {
            $this->feedbackService->delete($id);
            $this->addFlash('success', 'Feedback supprimé.');
        }
        return $this->redirectToRoute('employee_relation_feedbacks');
    }

    // ═══════════════════════════════════════════════════════════════
    // FEEDBACKS FORMATION
    // ═══════════════════════════════════════════════════════════════

    #[Route('/feedbacks-formation', name: 'ff_index')]
    public function ffIndex(Request $request): Response
    {
        $user       = $this->getUser();
        $employeeId = $user->getId();

        $ffList     = $this->ffService->getByUser($employeeId);
        $formations = $this->ffService->getApprovedFormations($employeeId);

        $formFilter = $request->query->get('formation', '');
        if ($formFilter) {
            $ffList = array_filter($ffList, fn($f) => (int)$f['formation_id'] === (int)$formFilter);
        }

        $avgRating = count($ffList) > 0
            ? round(array_sum(array_column(array_values($ffList), 'rating')) / count($ffList), 1)
            : 0;

        return $this->render('DashboardEmployee/Relation/feedbacks_formation.html.twig', [
            'ffList'      => array_values($ffList),
            'formations'  => $formations,
            'formFilter'  => $formFilter,
            'avgRating'   => $avgRating,
        ]);
    }

    #[Route('/feedbacks-formation/sessions', name: 'ff_sessions', methods: ['GET'])]
    public function ffSessions(Request $request): JsonResponse
    {
        $user        = $this->getUser();
        $formationId = (int)$request->query->get('formation_id', 0);
        $sessions    = $this->ffService->getApprovedSessionsForFormation($formationId, $user->getId());
        return new JsonResponse($sessions);
    }

    #[Route('/feedbacks-formation/new', name: 'ff_new', methods: ['POST'])]
    public function newFf(Request $request): Response
    {
        $user = $this->getUser();
        $data = $request->request->all();
        $data['user_id']   = $user->getId();
        $data['recommande'] = $request->request->has('recommande');

        $errors = $this->ffService->validateCreate($data);
        if (count($errors) > 0) {
            $viewData = $this->buildFeedbacksViewData($request, $user->getId());
            $viewData['tab'] = 'formation';
            $viewData['ffErrors'] = $errors;
            $viewData['ffOld'] = $data;
            return $this->render('DashboardEmployee/Relation/feedbacks.html.twig', $viewData, new Response(null, 422));
        }

        $this->ffService->add($data);
        $this->addFlash('success', 'Feedback formation envoyé. Merci !');
        return $this->redirectToRoute('employee_relation_feedbacks', ['tab' => 'formation']);
    }

    #[Route('/feedbacks-formation/{id}/edit', name: 'ff_edit', methods: ['POST'])]
    public function editFf(int $id, Request $request): Response
    {
        $user = $this->getUser();
        $ff   = $this->ffService->getById($id);
        if ($ff && (int)$ff['user_id'] === $user->getId()) {
            $data = $request->request->all();
            $data['recommande'] = $request->request->has('recommande');
            $errors = $this->ffService->validateUpdate($data);
            if (count($errors) > 0) {
                $viewData = $this->buildFeedbacksViewData($request, $user->getId());
                $viewData['tab'] = 'formation';
                $viewData['ffEditErrors'] = $errors;
                $viewData['ffEditOld'] = $data;
                $viewData['ffEditId'] = $id;
                return $this->render('DashboardEmployee/Relation/feedbacks.html.twig', $viewData, new Response(null, 422));
            }
            $this->ffService->update($id, $data);
            $this->addFlash('success', 'Feedback formation modifié.');
        }
        return $this->redirectToRoute('employee_relation_feedbacks', ['tab' => 'formation']);
    }

    #[Route('/feedbacks-formation/{id}/delete', name: 'ff_delete', methods: ['POST'])]
    public function deleteFf(int $id): Response
    {
        $user = $this->getUser();
        $ff   = $this->ffService->getById($id);
        if ($ff && (int)$ff['user_id'] === $user->getId()) {
            $this->ffService->delete($id);
            $this->addFlash('success', 'Feedback formation supprimé.');
        }
        return $this->redirectToRoute('employee_relation_feedbacks', ['tab' => 'formation']);
    }

    // ═══════════════════════════════════════════════════════════════
    // AJAX
    // ═══════════════════════════════════════════════════════════════

    #[Route('/requests/{id}/details', name: 'request_details', methods: ['GET'])]
    public function requestDetails(int $id): JsonResponse
    {
        $req = $this->requestService->getById($id);
        return new JsonResponse($req ?? []);
    }

    #[Route('/feedbacks/{id}/details', name: 'feedback_details', methods: ['GET'])]
    public function feedbackDetails(int $id): JsonResponse
    {
        $fb = $this->feedbackService->getById($id);
        if ($fb && $fb['is_anonymous']) $fb['from_username'] = 'Anonyme';
        if ($fb) $fb['rating_stars'] = $this->feedbackService->getRatingStars((int)$fb['rating']);
        return new JsonResponse($fb ?? []);
    }

    #[Route('/ff/{id}/details', name: 'ff_details', methods: ['GET'])]
    public function ffDetails(int $id): JsonResponse
    {
        $ff = $this->ffService->getById($id);
        if ($ff) $ff['rating_stars'] = $this->ffService->getRatingStars((int)$ff['rating']);
        return new JsonResponse($ff ?? []);
    }

    private function buildRequestsViewData(Request $request, int $employeeId): array
    {
        $allRequests  = $this->requestService->getByUserId($employeeId);
        $requestTypes = $this->requestTypeService->getAll();

        $statusFilter = $request->query->get('status', '');
        $search       = $request->query->get('search', '');
        $filtered     = $allRequests;

        if ($statusFilter) {
            $filtered = array_filter($filtered, fn($r) => $r['status'] === $statusFilter);
        }
        if ($search) {
            $filtered = array_filter($filtered, fn($r) =>
                stripos($r['title'], $search) !== false ||
                stripos($r['type_name'] ?? '', $search) !== false
            );
        }

        $stats = [
            'total'    => count($allRequests),
            'pending'  => count(array_filter($allRequests, fn($r) => $r['status'] === 'pending')),
            'approved' => count(array_filter($allRequests, fn($r) => $r['status'] === 'approved')),
            'rejected' => count(array_filter($allRequests, fn($r) => $r['status'] === 'rejected')),
        ];

        return [
            'requests'         => array_values($filtered),
            'requestTypes'     => $requestTypes,
            'stats'            => $stats,
            'statusFilter'     => $statusFilter,
            'search'           => $search,
            'requestErrors'    => [],
            'requestOld'       => [],
            'requestEditErrors' => [],
            'requestEditOld'   => [],
            'requestEditId'    => null,
        ];
    }

    private function buildFeedbacksViewData(Request $request, int $employeeId): array
    {
        $received = $this->feedbackService->getReceivedByEmployee($employeeId);
        $sent     = $this->feedbackService->getSentByEmployee($employeeId);
        $colleagues = $this->feedbackService->getColleagues($employeeId);

        $typeFilter = $request->query->get('type', '');
        if ($typeFilter) {
            $received = array_filter($received, fn($f) => $f['feedback_type'] === $typeFilter);
        }

        $avgRating = count($received) > 0
            ? round(array_sum(array_column(array_values($received), 'rating')) / count($received), 1)
            : 0;

        $ffList     = $this->ffService->getByUser($employeeId);
        $formations = $this->ffService->getApprovedFormations($employeeId);

        $formFilter = $request->query->get('formation', '');
        if ($formFilter) {
            $ffList = array_filter($ffList, fn($f) => (int)$f['formation_id'] === (int)$formFilter);
        }

        $ffAvgRating = count($ffList) > 0
            ? round(array_sum(array_column(array_values($ffList), 'rating')) / count($ffList), 1)
            : 0;

        $tab = $request->query->get('tab', 'employee');

        return [
            'received'      => array_values($received),
            'sent'          => $sent,
            'colleagues'    => $colleagues,
            'avgRating'     => $avgRating,
            'typeFilter'    => $typeFilter,
            'feedbackTypes' => ['performance', 'behavior', 'collaboration', 'other'],
            'ffList'        => array_values($ffList),
            'formations'    => $formations,
            'formFilter'    => $formFilter,
            'ffAvgRating'   => $ffAvgRating,
            'tab'           => $tab,
            'fbErrors'      => [],
            'fbOld'         => [],
            'fbEditErrors'  => [],
            'fbEditOld'     => [],
            'fbEditId'      => null,
            'ffErrors'      => [],
            'ffOld'         => [],
            'ffEditErrors'  => [],
            'ffEditOld'     => [],
            'ffEditId'      => null,
        ];
    }

    /** @return array{success: bool, message?: string} */
    private function validateRequestAttachment(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            return ['success' => false, 'message' => 'La pièce jointe est invalide.'];
        }

        if (($file->getSize() ?? 0) > 3 * 1024 * 1024) {
            return ['success' => false, 'message' => 'La pièce jointe dépasse 3 Mo.'];
        }

        $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
        $mime = $this->resolveAttachmentMimeType($file);
        $extension = $this->resolveAttachmentExtension($file);

        if (!in_array($mime, $allowedMimes, true) && !in_array($extension, $allowedExtensions, true)) {
            return ['success' => false, 'message' => 'Format non autorisé. Utilisez PDF, JPG ou PNG.'];
        }

        return ['success' => true];
    }

    /** @return array{success: bool, path?: string, message?: string} */
    private function storeRequestAttachment(UploadedFile $file): array
    {
        try {
            $targetDir = $this->getParameter('kernel.project_dir') . '/public/uploads/requests';
            if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                return ['success' => false, 'message' => 'Impossible de créer le dossier des pièces jointes.'];
            }

            $extension = $this->resolveAttachmentExtension($file);
            if ($extension === '') {
                $extension = 'bin';
            }
            $fileName = 'request_' . uniqid('', true) . '.' . $extension;
            $file->move($targetDir, $fileName);

            return ['success' => true, 'path' => '/uploads/requests/' . $fileName];
        } catch (\Throwable) {
            return ['success' => false, 'message' => 'Échec du téléversement de la pièce jointe.'];
        }
    }

    private function mapUploadError(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La pièce jointe dépasse la taille autorisée.',
            UPLOAD_ERR_PARTIAL => 'La pièce jointe a été envoyée partiellement. Réessayez.',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant sur le serveur.',
            UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire le fichier sur le disque.',
            UPLOAD_ERR_EXTENSION => 'Téléversement bloqué par une extension PHP.',
            default => 'Téléversement de la pièce jointe échoué.',
        };
    }

    private function extractAttachmentFile(Request $request): ?UploadedFile
    {
        $directFile = $request->files->get('attachment');
        if ($directFile instanceof UploadedFile) {
            return $directFile;
        }

        $allFiles = $request->files->all();
        if (isset($allFiles['attachment']) && $allFiles['attachment'] instanceof UploadedFile) {
            return $allFiles['attachment'];
        }

        if (isset($_FILES['attachment']) && is_array($_FILES['attachment'])) {
            $tmpName = (string) ($_FILES['attachment']['tmp_name'] ?? '');
            $errorCode = (int) ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($tmpName !== '' && is_file($tmpName) && $errorCode === UPLOAD_ERR_OK) {
                $originalName = (string) ($_FILES['attachment']['name'] ?? 'attachment.bin');
                $mimeType = (string) ($_FILES['attachment']['type'] ?? 'application/octet-stream');

                return new UploadedFile($tmpName, $originalName, $mimeType, $errorCode, true);
            }
        }

        return null;
    }

    private function extractAttachmentUploadError(): ?int
    {
        if (!isset($_FILES['attachment']) || !is_array($_FILES['attachment'])) {
            return null;
        }

        return (int) ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE);
    }

    private function resolveAttachmentMimeType(UploadedFile $file): string
    {
        try {
            $mime = $file->getMimeType();
            if (is_string($mime) && $mime !== '') {
                return strtolower($mime);
            }
        } catch (\Throwable) {
            // Fallback when php_fileinfo is not available.
        }

        $clientMime = $file->getClientMimeType();
        return is_string($clientMime) ? strtolower(trim($clientMime)) : '';
    }

    private function resolveAttachmentExtension(UploadedFile $file): string
    {
        $clientExtension = $file->getClientOriginalExtension();
        if (is_string($clientExtension) && $clientExtension !== '') {
            return strtolower(trim($clientExtension));
        }

        $originalName = $file->getClientOriginalName();
        if (!is_string($originalName) || $originalName === '') {
            return '';
        }

        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        return is_string($ext) ? strtolower(trim($ext)) : '';
    }
}
