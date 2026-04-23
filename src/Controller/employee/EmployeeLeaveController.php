<?php

namespace App\Controller\employee;

use App\Security\DbUser;
use App\Service\Rh\LeaveBalanceService;
use App\Service\Rh\PublicHolidayService;
use App\Service\Rh\LeaveRequestService;
use App\Service\Shared\AiService;
use App\Service\Shared\MedicalCertificateOcrService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class EmployeeLeaveController extends AbstractController
{
    #[Route('/welcome/employee/leaves', name: 'app_employee_leave_requests', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function index(
        Request $request,
        LeaveRequestService $leaveRequestService,
        LeaveBalanceService $leaveBalanceService,
        PublicHolidayService $publicHolidayService,
        MedicalCertificateOcrService $medicalCertificateOcrService,
    ): Response {
        if ($request->isMethod('POST')) {
            $redirect = $this->handleSubmitRequest($request, $leaveRequestService, $medicalCertificateOcrService);
            if ($redirect !== null) {
                return $redirect;
            }
        }

        $employeeId = $this->getCurrentEmployeeId();

        $today = new DateTimeImmutable('today');
        $calendarEnd = $today->modify('+12 months');
        $blockedHolidayDates = $publicHolidayService->getHolidayDatesInRange($today, $calendarEnd);

        sort($blockedHolidayDates);
        $blockedHolidayDates = array_values(array_unique($blockedHolidayDates));

        $blockedLeaveDates = $leaveRequestService->getEmployeeBlockedLeaveDates($employeeId);

        return $this->render('DashboardEmployee/leave_requests.html.twig', [
            'user' => $this->getUser(),
            'leaveRequests' => $leaveRequestService->getEmployeeRequests($employeeId),
            'balance' => $leaveBalanceService->getEmployeeBalance($employeeId),
            'leaveStats' => $leaveRequestService->getEmployeeDashboardStats($employeeId),
            'pendingLeaveCount' => $leaveRequestService->getEmployeePendingCount($employeeId),
            'blockedHolidayDates' => $blockedHolidayDates,
            'blockedLeaveDates' => $blockedLeaveDates,
            'isOnLeave' => $leaveRequestService->isEmployeeCurrentlyOnLeave($employeeId),
            'calendarLeaves' => $leaveRequestService->getEmployeeCalendarLeaves($employeeId),
        ]);
    }

    #[Route('/welcome/employee/leaves/{id}/delete', name: 'app_employee_leave_request_delete', methods: ['POST'])]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function delete(
        string $id,
        Request $request,
        LeaveRequestService $leaveRequestService,
    ): RedirectResponse {
        $idInt = (int) $id;
        if (!$this->isCsrfTokenValid('employee_leave_delete_' . $idInt, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_employee_leave_requests');
        }

        $deleted = $leaveRequestService->deleteEmployeePendingRequest($this->getCurrentEmployeeId(), $idInt);

        if (!$deleted) {
            $this->addFlash('error', 'Seules les demandes en attente peuvent etre supprimees.');
            return $this->redirectToRoute('app_employee_leave_requests');
        }

        $this->addFlash('success', 'Demande supprimee avec succes.');
        return $this->redirectToRoute('app_employee_leave_requests');
    }

    #[Route('/welcome/employee/leaves/ai/justification', name: 'app_employee_leave_ai_justification', methods: ['POST'])]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function aiJustification(Request $request, AiService $aiService): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['success' => false, 'message' => 'Payload JSON invalide.'], 400);
        }

        if (!$this->isCsrfTokenValid('employee_leave_ai_justification', (string) ($payload['_token'] ?? ''))) {
            return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide.'], 403);
        }

        $startDate = trim((string) ($payload['start_date'] ?? ''));
        $endDate = trim((string) ($payload['end_date'] ?? ''));
        if ($startDate === '' || $endDate === '') {
            return new JsonResponse(['success' => false, 'message' => 'Les dates sont obligatoires.'], 422);
        }

        $suggestion = $aiService->generateEmployeeLeaveJustification([
            'leave_type' => trim((string) ($payload['leave_type'] ?? 'Conge exceptionnel')),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'urgency_level' => trim((string) ($payload['urgency_level'] ?? '')),
            'reason' => trim((string) ($payload['reason'] ?? '')),
        ]);

        return new JsonResponse([
            'success' => true,
            'suggestion' => $suggestion,
            'notice' => 'Texte suggere par IA, a verifier avant envoi.',
        ]);
    }

    private function handleSubmitRequest(
        Request $request,
        LeaveRequestService $leaveRequestService,
        MedicalCertificateOcrService $medicalCertificateOcrService,
    ): ?RedirectResponse
    {
        if (!$this->isCsrfTokenValid('employee_leave_submit', (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_employee_leave_requests');
        }

        $startDate = trim((string) $request->request->get('start_date', ''));
        $endDate = trim((string) $request->request->get('end_date', ''));
        $leaveType = trim((string) $request->request->get('leave_type', ''));
        $reason = trim((string) $request->request->get('reason', ''));
        $requestMode = strtoupper(trim((string) $request->request->get('request_mode', LeaveRequestService::CATEGORY_NORMAL)));
        $urgencyLevel = trim((string) $request->request->get('urgency_level', ''));
        $justificatifText = trim((string) $request->request->get('justificatif_text', ''));
        $attachmentOcrText = null;
        $attachmentOcrSummary = null;

        $attachmentPath = null;
        if ($requestMode === LeaveRequestService::CATEGORY_EXCEPTION) {
            $file = $request->files->get('justificatif');

            $hasTextJustification = $justificatifText !== '';
            $hasFileJustification = $file instanceof UploadedFile;
            if (!$hasTextJustification && !$hasFileJustification) {
                $this->addFlash('error', 'Ajoutez un justificatif texte ou un fichier pour la demande exceptionnelle.');
                return $this->redirectToRoute('app_employee_leave_requests');
            }

            if ($hasFileJustification) {
                $fileMimeType = $this->resolveAttachmentMimeType($file);
                $validation = $this->validateAttachment($file);
                if (!$validation['success']) {
                    $this->addFlash('error', (string) $validation['message']);
                    return $this->redirectToRoute('app_employee_leave_requests');
                }

                $upload = $this->storeAttachment($file);
                if (!$upload['success']) {
                    $this->addFlash('error', (string) $upload['message']);
                    return $this->redirectToRoute('app_employee_leave_requests');
                }

                $attachmentPath = (string) $upload['path'];

                $ocr = $medicalCertificateOcrService->extractFromAttachment(
                    (string) ($upload['absolute_path'] ?? ''),
                    $fileMimeType,
                );
                $attachmentOcrText = $ocr['text'] ?? null;
                $attachmentOcrSummary = $ocr['summary'] ?? null;
            }

            if ($hasTextJustification) {
                $reason = $reason !== ''
                    ? $reason . "\n\nJustificatif texte: " . $justificatifText
                    : 'Justificatif texte: ' . $justificatifText;
            }
        }

        $result = $leaveRequestService->submitEmployeeRequest(
            $this->getCurrentEmployeeId(),
            $startDate,
            $endDate,
            $leaveType,
            $reason,
            $requestMode,
            $urgencyLevel,
            $attachmentPath,
            $attachmentOcrText,
            $attachmentOcrSummary,
        );

        $this->addFlash($result['success'] ? 'success' : 'error', (string) $result['message']);
        return $this->redirectToRoute('app_employee_leave_requests');
    }

    private function getCurrentEmployeeId(): int
    {
        $user = $this->getUser();

        if (!$user instanceof DbUser || !$user->isEmployee()) {
            throw $this->createAccessDeniedException('Utilisateur employe invalide.');
        }

        return $user->getId();
    }

    /** @return array{success: bool, message?: string} */
    private function validateAttachment(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            return ['success' => false, 'message' => 'Le justificatif est invalide.'];
        }

        if (($file->getSize() ?? 0) > 3 * 1024 * 1024) {
            return ['success' => false, 'message' => 'Le justificatif depasse 3 Mo.'];
        }

        $mime = $this->resolveAttachmentMimeType($file);
        $allowed = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!in_array($mime, $allowed, true)) {
            return ['success' => false, 'message' => 'Format non autorise. Utilisez PDF, JPG ou PNG.'];
        }

        return ['success' => true];
    }

    private function resolveAttachmentMimeType(UploadedFile $file): string
    {
        try {
            $detectedMime = strtolower(trim((string) $file->getMimeType()));
            if ($detectedMime !== '') {
                return $detectedMime;
            }
        } catch (\Throwable) {
            // Fallback below when fileinfo/mime guesser is unavailable.
        }

        $clientMime = strtolower(trim((string) $file->getClientMimeType()));
        if ($clientMime !== '') {
            return $clientMime;
        }

        $extension = strtolower(trim((string) $file->getClientOriginalExtension()));

        return match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => '',
        };
    }

    /** @return array{success: bool, path?: string, absolute_path?: string, message?: string} */
    private function storeAttachment(UploadedFile $file): array
    {
        try {
            $targetDir = $this->getParameter('kernel.project_dir') . '/public/uploads/leave-exceptions';
            if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                return ['success' => false, 'message' => 'Impossible de creer le dossier justificatifs.'];
            }

            $extension = $file->guessExtension() ?: 'bin';
            $fileName = 'leave_exception_' . uniqid('', true) . '.' . $extension;
            $file->move($targetDir, $fileName);

            return [
                'success' => true,
                'path' => '/uploads/leave-exceptions/' . $fileName,
                'absolute_path' => $targetDir . '/' . $fileName,
            ];
        } catch (\Throwable) {
            return ['success' => false, 'message' => 'Echec de televersement du justificatif.'];
        }
    }
}
