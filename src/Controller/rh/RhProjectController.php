<?php

namespace App\Controller\rh;

use App\Security\DbUser;
use App\Service\Projet\ProjectService;
use App\Service\Projet\ProjectTaskService;
use App\Service\Projet\ProjectCollaboratorService;
use App\Service\Projet\ProjectMilestoneService;
use App\Service\Projet\ProjectUpdateService;
use App\Service\Projet\ProjectReportPdfService;
use App\Service\Projet\TaskDeadlineAlertService;
use App\Service\Shared\AiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/rh/projects', name: 'rh_project_')]
class RhProjectController extends AbstractController
{
    public function __construct(
        private readonly ProjectService $projectService,
        private readonly ProjectTaskService $taskService,
        private readonly ProjectCollaboratorService $collaboratorService,
        private readonly ProjectMilestoneService $milestoneService,
        private readonly ProjectUpdateService $updateService,
        private readonly ProjectReportPdfService $projectReportPdfService,
        private readonly TaskDeadlineAlertService $taskDeadlineAlertService,
        private readonly AiService $aiService,
    ) {}

    // ═══════════════════════════════════════════════════════════════
    // LISTE DES PROJETS
    // ═══════════════════════════════════════════════════════════════

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $user = $this->getDbUser();
        $rhId = $user->getId();

        $projects = $this->projectService->getProjectsWithDetails($rhId);
        $stats = $this->projectService->getProjectStats($rhId);

        // Filtres
        $statusFilter = trim((string) $request->query->get('status', ''));
        $priorityFilter = trim((string) $request->query->get('priority', ''));
        $search = trim((string) $request->query->get('search', ''));

        $projects = $this->applyProjectFilters($projects, $statusFilter, $priorityFilter, $search);

        return $this->render('DashboardHr/Project/index.html.twig', [
            'projects' => array_values($projects),
            'stats' => $stats,
            'statusFilter' => $statusFilter,
            'priorityFilter' => $priorityFilter,
            'search' => $search,
            'statuses' => ['planning', 'in_progress', 'on_hold', 'completed', 'cancelled'],
            'priorities' => ['low', 'medium', 'high', 'critical'],
        ]);
    }

    #[Route('/export/pdf', name: 'export_pdf', methods: ['GET'])]
    public function exportPdf(Request $request): Response
    {
        $user = $this->getDbUser();
        $rhId = $user->getId();

        $statusFilter = trim((string) $request->query->get('status', ''));
        $priorityFilter = trim((string) $request->query->get('priority', ''));
        $search = trim((string) $request->query->get('search', ''));

        $projects = $this->projectService->getProjectsWithDetails($rhId);
        $projects = array_values($this->applyProjectFilters($projects, $statusFilter, $priorityFilter, $search));

        $reportProjects = [];
        foreach ($projects as $project) {
            $projectId = (int) ($project['id'] ?? 0);
            if ($projectId <= 0) {
                continue;
            }

            $tasks = $this->taskService->getTasksByProject($projectId);
            $team = $this->collaboratorService->getCollaboratorsByProject($projectId);
            $milestones = $this->milestoneService->getMilestonesByProject($projectId);
            $analytics = $this->calculateAnalytics($project, $tasks, $team, $milestones);

            $reportProjects[] = [
                'project' => $project,
                'tasks' => $tasks,
                'team' => $team,
                'milestones' => $milestones,
                'analytics' => $analytics,
            ];
        }

        $stats = $this->projectService->getProjectStats($rhId);
        $pdf = $this->projectReportPdfService->generatePdf([
            'reportProjects' => $reportProjects,
            'stats' => $stats,
            'filters' => [
                'status' => $statusFilter,
                'priority' => $priorityFilter,
                'search' => $search,
            ],
            'generatedAt' => new \DateTimeImmutable(),
            'rhId' => $rhId,
        ]);

        return new Response($pdf['content'], Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $pdf['fileName'] . '"',
        ]);
    }

    #[Route('/alerts/send-task-reminders', name: 'send_task_reminders', methods: ['POST'])]
    public function sendTaskReminders(Request $request): Response
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('rh-project-send-task-reminders', $token)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('rh_project_index');
        }

        $user = $this->getDbUser();
        $rhId = $user->getId();

        $result = $this->taskDeadlineAlertService->sendAlertsForRh($rhId);

        if ($result['tasksFlagged'] === 0) {
            $this->addFlash('success', 'Aucune alerte a envoyer pour le moment.');
        } else {
            $this->addFlash(
                'success',
                sprintf(
                    'Alertes envoyees: %d tache(s), %d email(s) employe, %d email RH.',
                    (int) $result['tasksFlagged'],
                    (int) $result['employeeEmailsSent'],
                    (int) $result['rhEmailsSent']
                )
            );
        }

        return $this->redirectToRoute('rh_project_index');
    }

    // ═══════════════════════════════════════════════════════════════
    // DÉTAILS D'UN PROJET
    // ═══════════════════════════════════════════════════════════════

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        return $this->renderProjectShow($id);
    }

    // ═══════════════════════════════════════════════════════════════
    // CRÉER UN PROJET
    // ═══════════════════════════════════════════════════════════════

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $user = $this->getDbUser();

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $data['rh_id'] = $user->getId();


            $errors = $this->projectService->validate($data);


            if (count($errors) > 0) {
                foreach ($errors as $field => $message) {

                    $this->addFlash('error', $message);
                }


                return $this->render('DashboardHr/Project/new.html.twig', [
                    'statuses' => ['planning', 'in_progress', 'on_hold', 'completed', 'cancelled'],
                    'priorities' => ['low', 'medium', 'high', 'critical'],
                    'old_data' => $data,
                    'errors' => $errors
                ], new Response(null, 422));
            }


            $this->projectService->createProject($data);
            $this->addFlash('success', 'Projet créé avec succès !');
            return $this->redirectToRoute('rh_project_index');
        }

        return $this->render('DashboardHr/Project/new.html.twig', [
            'statuses' => ['planning', 'in_progress', 'on_hold', 'completed', 'cancelled'],
            'priorities' => ['low', 'medium', 'high', 'critical'],
            'old_data' => [],
            'errors' => []
        ]);
    }
    // ═══════════════════════════════════════════════════════════════
    // MODIFIER UN PROJET
    // ═══════════════════════════════════════════════════════════════

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\\d+'])]
    public function edit(int $id, Request $request): Response
    {
        $project = $this->projectService->getProjectById($id);
        if (!$project) {
            throw $this->createNotFoundException('Projet non trouvé');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $errors = $this->projectService->validate($data);

            if (count($errors) > 0) {
                foreach ($errors as $message) {
                    $this->addFlash('error', $message);
                }

                return $this->render('DashboardHr/Project/edit.html.twig', [
                    'project' => $project,
                    'statuses' => ['planning', 'in_progress', 'on_hold', 'completed', 'cancelled'],
                    'priorities' => ['low', 'medium', 'high', 'critical'],
                    'old_data' => $data,
                    'errors' => $errors,
                ], new Response(null, 422));
            }

            $this->projectService->updateProject($id, $data);
            $this->addFlash('success', 'Projet modifié avec succès !');
            return $this->redirectToRoute('rh_project_show', ['id' => $id]);
        }

        return $this->render('DashboardHr/Project/edit.html.twig', [
            'project' => $project,
            'statuses' => ['planning', 'in_progress', 'on_hold', 'completed', 'cancelled'],
            'priorities' => ['low', 'medium', 'high', 'critical'],
            'old_data' => [],
            'errors' => [],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // SUPPRIMER UN PROJET
    // ═══════════════════════════════════════════════════════════════

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id): Response
    {
        $project = $this->projectService->getProjectById($id);
        if ($project) {
            $this->projectService->deleteProject($id);
            $this->addFlash('success', 'Projet supprimé.');
        }
        return $this->redirectToRoute('rh_project_index');
    }

    // ═══════════════════════════════════════════════════════════════
    // GESTION DES TÂCHES
    // ═══════════════════════════════════════════════════════════════

    #[Route('/{id}/tasks/new', name: 'task_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function newTask(int $id, Request $request): Response
    {
        $user = $this->getDbUser();
        $data = $request->request->all();
        $data['project_id'] = $id;

        $errors = $this->taskService->validate($data);
        if (count($errors) > 0) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'errors' => $errors,
                    'message' => 'Veuillez corriger les erreurs du formulaire.',
                ], 422);
            }

            return $this->renderProjectShow($id, [
                'taskCreateErrors' => $errors,
                'taskCreateData' => $data,
                'activeTab' => 'kanban',
                'openModal' => 'modalNewTask',
            ], 422);
        }

        $this->taskService->createTask($data);
        $taskTitle = is_string($data['title'] ?? null) ? $data['title'] : (is_scalar($data['title'] ?? null) ? (string) $data['title'] : 'Untitled Task');
        $this->updateService->logTaskCreated($id, $user->getId(), $taskTitle, true);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Tache creee avec succes.',
            ]);
        }

        $this->addFlash('success', 'Tâche créée !');
        return $this->redirectToRoute('rh_project_show', ['id' => $id, '_fragment' => 'kanban']);
    }

    #[Route('/{id}/tasks/{taskId}/edit', name: 'task_edit', requirements: ['id' => '\d+', 'taskId' => '\d+'], methods: ['POST'])]
    public function editTask(int $id, int $taskId, Request $request): Response
    {
        $data = $request->request->all();
        $data['project_id'] = $id;

        $errors = $this->taskService->validate($data);
        if (count($errors) > 0) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'errors' => $errors,
                    'message' => 'Veuillez corriger les erreurs du formulaire.',
                ], 422);
            }

            $task = $this->taskService->getTaskById($taskId);

            return $this->renderProjectShow($id, [
                'taskEditErrors' => $errors,
                'taskEditData' => $data,
                'taskEditTaskId' => $taskId,
                'taskEditOriginal' => $task,
                'activeTab' => 'kanban',
                'openModal' => 'modalEditTask',
            ], 422);
        }

        $this->taskService->updateTask($taskId, $data);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Tache modifiee avec succes.',
            ]);
        }

        $this->addFlash('success', 'Tâche modifiée !');
        return $this->redirectToRoute('rh_project_show', ['id' => $id, '_fragment' => 'kanban']);
    }

    #[Route('/{id}/tasks/{taskId}/move', name: 'task_move', requirements: ['id' => '\d+', 'taskId' => '\d+'], methods: ['POST'])]
    public function moveTask(int $id, int $taskId, Request $request): JsonResponse
    {
        $user = $this->getDbUser();
        $newStatus = trim((string) $request->request->get('status', ''));
        $task = $this->taskService->getTaskById($taskId);

        if ($task && $newStatus !== '') {
            $this->taskService->updateTaskStatus($taskId, $newStatus);
            if ($newStatus === 'done') {
                $this->updateService->logTaskCompleted($id, $user->getId(), $task['title'], true);
            }
            $this->projectService->updateCompletionRate($id);
            return new JsonResponse(['success' => true]);
        }
        return new JsonResponse(['success' => false], 400);
    }

    #[Route('/{id}/tasks/{taskId}/delete', name: 'task_delete', requirements: ['id' => '\d+', 'taskId' => '\d+'], methods: ['POST'])]
    public function deleteTask(int $id, int $taskId): Response
    {
        $this->taskService->deleteTask($taskId);
        $this->addFlash('success', 'Tâche supprimée.');
        return $this->redirectToRoute('rh_project_show', ['id' => $id, '_fragment' => 'kanban']);
    }

    #[Route('/{id}/tasks/{taskId}/ai-suggestions', name: 'task_ai_suggestions', requirements: ['id' => '\d+', 'taskId' => '\d+'], methods: ['GET'])]
    public function aiTaskSuggestions(int $id, int $taskId): JsonResponse
    {
        $task = $this->taskService->getTaskById($taskId);
        if (!$task || (int) ($task['project_id'] ?? 0) !== $id) {
            return new JsonResponse(['success' => false, 'message' => 'Tache introuvable.'], 404);
        }

        $candidates = $this->taskService->getProjectAssigneeCandidates($id);
        if ($candidates === []) {
            return new JsonResponse(['success' => false, 'message' => 'Aucun collaborateur actif disponible.'], 422);
        }

        $aiSuggestions = $this->aiService->suggestTaskAssignees($task, $candidates);
        $suggestions = $this->buildTaskSuggestions($aiSuggestions, $candidates, $task);

        return new JsonResponse([
            'success' => true,
            'suggestions' => $suggestions,
        ]);
    }

    #[Route('/{id}/tasks/{taskId}/assign', name: 'task_assign', requirements: ['id' => '\d+', 'taskId' => '\d+'], methods: ['POST'])]
    public function assignTask(int $id, int $taskId, Request $request): JsonResponse
    {
        $task = $this->taskService->getTaskById($taskId);
        if (!$task || (int) ($task['project_id'] ?? 0) !== $id) {
            return new JsonResponse(['success' => false, 'message' => 'Tache introuvable.'], 404);
        }

        $employeeId = (int) $request->request->get('employee_id', 0);
        if ($employeeId <= 0) {
            return new JsonResponse(['success' => false, 'message' => 'Employe invalide.'], 422);
        }

        if (!$this->collaboratorService->isCollaborator($id, $employeeId)) {
            return new JsonResponse(['success' => false, 'message' => 'Employe non membre actif de ce projet.'], 422);
        }

        $ok = $this->taskService->assignTaskToEmployee($taskId, $employeeId);
        if (!$ok) {
            return new JsonResponse(['success' => false, 'message' => 'Impossible d\'assigner cette tache.'], 500);
        }

        return new JsonResponse(['success' => true, 'message' => 'Tache assignee avec succes.']);
    }

    // ═══════════════════════════════════════════════════════════════
    // GESTION DE L'ÉQUIPE
    // ═══════════════════════════════════════════════════════════════

    #[Route('/{id}/team/add', name: 'team_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addTeamMember(int $id, Request $request): Response
    {
        $data = $request->request->all();
        $data['project_id'] = $id;

        $result = $this->collaboratorService->addCollaborator($data);
        if (($result['success'] ?? false) === true) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'message' => $result['message'] ?? 'Membre ajoute avec succes.',
                ]);
            }

            $this->addFlash('success', $result['message'] ?? 'Membre ajoute a l\'equipe !');
            return $this->redirectToRoute('rh_project_show', ['id' => $id, '_fragment' => 'team']);
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => false,
                'errors' => $result['errors'] ?? ['_global' => ($result['message'] ?? 'Impossible d\'ajouter ce membre.')],
                'message' => $result['message'] ?? 'Impossible d\'ajouter ce membre.',
            ], 422);
        }

        return $this->renderProjectShow($id, [
            'teamAddErrors' => $result['errors'] ?? ['_global' => ($result['message'] ?? 'Impossible d\'ajouter ce membre.')],
            'teamAddData' => $result['data'] ?? $data,
            'activeTab' => 'team',
            'openModal' => 'modalAddMember',
        ], 422);
    }

    #[Route('/{id}/team/{collabId}/remove', name: 'team_remove', requirements: ['id' => '\d+', 'collabId' => '\d+'], methods: ['POST'])]
    public function removeTeamMember(int $id, int $collabId): Response
    {
        $this->collaboratorService->removeCollaborator($id, $collabId);
        $this->addFlash('success', 'Membre retiré du projet.');
        return $this->redirectToRoute('rh_project_show', ['id' => $id, '_fragment' => 'team']);
    }

    // ═══════════════════════════════════════════════════════════════
    // GESTION DES JALONS
    // ═══════════════════════════════════════════════════════════════

    #[Route('/{id}/milestones/new', name: 'milestone_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function newMilestone(int $id, Request $request): Response
    {
        $data = $request->request->all();
        $data['project_id'] = $id;
        $this->milestoneService->createMilestone($data);
        $this->addFlash('success', 'Jalon créé !');
        return $this->redirectToRoute('rh_project_show', ['id' => $id, '_fragment' => 'milestones']);
    }

    #[Route('/{id}/milestones/{milestoneId}/edit', name: 'milestone_edit', requirements: ['id' => '\d+', 'milestoneId' => '\d+'], methods: ['POST'])]
    public function editMilestone(int $id, int $milestoneId, Request $request): Response
    {
        $data = $request->request->all();
        $this->milestoneService->updateMilestone($milestoneId, $data);
        $this->addFlash('success', 'Jalon modifié !');
        return $this->redirectToRoute('rh_project_show', ['id' => $id, '_fragment' => 'milestones']);
    }

    #[Route('/{id}/milestones/{milestoneId}/complete', name: 'milestone_complete', requirements: ['id' => '\d+', 'milestoneId' => '\d+'], methods: ['POST'])]
    public function completeMilestone(int $id, int $milestoneId): Response
    {
        $user = $this->getDbUser();
        $milestone = $this->milestoneService->getMilestoneById($milestoneId);
        $this->milestoneService->markAsCompleted($milestoneId);
        if ($milestone) {
            $this->updateService->logMilestoneCompleted($id, $user->getId(), $milestone['name'], true);
        }
        $this->addFlash('success', 'Jalon marqué comme terminé !');
        return $this->redirectToRoute('rh_project_show', ['id' => $id, '_fragment' => 'milestones']);
    }

    #[Route('/{id}/milestones/{milestoneId}/delete', name: 'milestone_delete', requirements: ['id' => '\d+', 'milestoneId' => '\d+'], methods: ['POST'])]
    public function deleteMilestone(int $id, int $milestoneId): Response
    {
        $this->milestoneService->deleteMilestone($milestoneId);
        $this->addFlash('success', 'Jalon supprimé.');
        return $this->redirectToRoute('rh_project_show', ['id' => $id, '_fragment' => 'milestones']);
    }

    // ═══════════════════════════════════════════════════════════════
    // ACTIVITÉS / COMMENTAIRES
    // ═══════════════════════════════════════════════════════════════

    #[Route('/{id}/comment', name: 'comment', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addComment(int $id, Request $request): Response
    {
        $user = $this->getDbUser();
        $content = $request->request->get('content');

        if ($content) {
            $this->updateService->createUpdate([
                'project_id' => $id,
                'user_id' => $user->getId(),
                'update_type' => 'rh_comment',
                'actor_source' => 'rh',
                'title' => 'Commentaire RH',
                'content' => $content,
            ]);
            $this->addFlash('success', 'Commentaire publié !');
        }

        return $this->redirectToRoute('rh_project_show', ['id' => $id, '_fragment' => 'activity']);
    }

    // ═══════════════════════════════════════════════════════════════
    // ANALYTICS HELPER
    // ═══════════════════════════════════════════════════════════════

    /** @param array<string, mixed> $formState */
    private function renderProjectShow(int $id, array $formState = [], int $status = 200): Response
    {
        /** @var array<string, mixed> $formState */
        $user = $this->getDbUser();
        $rhId = $user->getId();

        $project = $this->projectService->getProjectById($id);
        if (!$project) {
            throw $this->createNotFoundException('Projet non trouvé');
        }

        $tasks = $this->taskService->getTasksByProject($id);
        $team = $this->collaboratorService->getCollaboratorsByProject($id);
        $milestones = $this->milestoneService->getMilestonesByProject($id);
        $updates = $this->updateService->getUpdatesByProject($id, 20);
        $milestoneStats = $this->milestoneService->getProjectMilestoneStats($id);

        $tasksByStatus = [
            'todo' => [],
            'in_progress' => [],
            'review' => [],
            'done' => [],
        ];
        foreach ($tasks as $task) {
            $taskStatus = $task['status'] ?? 'todo';
            if (isset($tasksByStatus[$taskStatus])) {
                $tasksByStatus[$taskStatus][] = $task;
            }
        }

        $analytics = $this->calculateAnalytics($project, $tasks, $team, $milestones);
        $availableEmployees = $this->collaboratorService->getAvailableEmployees($id, $rhId);
        $remainingAssignableHours = $this->collaboratorService->getRemainingAssignableHours($id);

        return $this->render('DashboardHr/Project/show.html.twig', array_merge([
            'project' => $project,
            'tasks' => $tasks,
            'tasksByStatus' => $tasksByStatus,
            'team' => $team,
            'milestones' => $milestones,
            'updates' => $updates,
            'milestoneStats' => $milestoneStats,
            'analytics' => $analytics,
            'availableEmployees' => $availableEmployees,
            'remainingAssignableHours' => $remainingAssignableHours,
            'taskCreateErrors' => [],
            'taskCreateData' => [],
            'taskEditErrors' => [],
            'taskEditData' => [],
            'taskEditTaskId' => null,
            'taskEditOriginal' => null,
            'teamAddErrors' => [],
            'teamAddData' => [],
            'activeTab' => null,
            'openModal' => null,
        ], $formState), new Response(null, $status));
    }

    /**
     * @param array<string, mixed> $project
     * @param array<int, array<string, mixed>> $tasks
     * @param array<int, array<string, mixed>> $team
     * @param array<int, array<string, mixed>> $milestones
     * @return array<string, mixed>
     */
    private function calculateAnalytics(array $project, array $tasks, array $team, array $milestones): array
    {
        $totalTasks = count($tasks);
        $completedTasks = count(array_filter($tasks, fn($t) => $t['status'] === 'done'));
        $overdueTasks = count(array_filter($tasks, fn($t) => $this->taskService->isOverdue($t)));
        $unassignedTasks = count(array_filter($tasks, fn($t) => empty($t['assigned_to']) && $t['status'] !== 'done'));

        // Vélocité (tâches terminées / jours écoulés)
        $startDate = new \DateTime($project['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
        $now = new \DateTime();
        $daysElapsed = max(1, $startDate->diff($now)->days);
        $velocity = $completedTasks > 0 ? round($completedTasks / $daysElapsed, 2) : 0;

        // Prédiction date de fin
        $prediction = null;
        if ($velocity > 0 && $totalTasks > $completedTasks) {
            $remainingTasks = $totalTasks - $completedTasks;
            $daysNeeded = (int) ceil($remainingTasks / $velocity);
            $predictedEnd = (new \DateTime())->modify("+{$daysNeeded} days");
            $plannedEnd = new \DateTime($project['end_date']);
            $diff = $predictedEnd->diff($plannedEnd);
            $diffDays = $diff->days === false ? 0 : $diff->days;
            $daysDiff = $predictedEnd > $plannedEnd ? -$diffDays : $diffDays;

            $prediction = [
                'predicted_date' => $predictedEnd->format('Y-m-d'),
                'days_diff' => $daysDiff,
                'velocity' => $velocity,
                'remaining_tasks' => $remainingTasks,
                'days_needed' => $daysNeeded,
                'status' => $daysDiff >= 7 ? 'early' : ($daysDiff <= -7 ? 'late' : 'on_track'),
            ];
        }

        // Score de santé
        $healthScore = 100;
        $warnings = [];
        $criticals = [];

        // Vérifier progression vs temps écoulé
        $endDate = new \DateTime($project['end_date']);
        $totalDays = max(1, $startDate->diff($endDate)->days);
        $expectedProgress = min(100, ($daysElapsed / $totalDays) * 100);
        $actualProgress = (int)($project['completion_rate'] ?? 0);
        $gap = $expectedProgress - $actualProgress;

        if ($gap > 20) {
            $healthScore -= 25;
            $criticals[] = '⚠️ Retard significatif: ' . round($gap) . '% de retard';
        } elseif ($gap > 10) {
            $healthScore -= 15;
            $warnings[] = '⚠️ Léger retard: ' . round($gap) . '% de retard';
        }

        if ($overdueTasks > 5) {
            $healthScore -= 20;
            $criticals[] = '🔴 ' . $overdueTasks . ' tâches en retard';
        } elseif ($overdueTasks > 0) {
            $healthScore -= 10;
            $warnings[] = '🟡 ' . $overdueTasks . ' tâche(s) en retard';
        }

        $delayedMilestones = count(array_filter($milestones, fn($m) => $this->milestoneService->isOverdue($m)));
        if ($delayedMilestones > 0) {
            $healthScore -= 15;
            $warnings[] = '🎯 ' . $delayedMilestones . ' jalon(s) en retard';
        }

        if ($unassignedTasks > 0) {
            $healthScore -= 5;
            $warnings[] = '👤 ' . $unassignedTasks . ' tâche(s) non assignée(s)';
        }

        $healthScore = max(0, $healthScore);
        $healthStatus = $healthScore >= 80 ? 'excellent' : ($healthScore >= 60 ? 'good' : ($healthScore >= 40 ? 'warning' : 'critical'));


        $risks = [];
        if ($unassignedTasks > 0) {
            $risks[] = ['severity' => 'medium', 'title' => 'Tâches non assignées', 'description' => $unassignedTasks . ' tâche(s) sans responsable', 'recommendation' => 'Assigner ces tâches aux membres de l\'équipe'];
        }
        if ($prediction && $prediction['status'] === 'late') {
            $risks[] = ['severity' => 'high', 'title' => 'Retard prévu', 'description' => 'Retard estimé de ' . abs($prediction['days_diff']) . ' jours', 'recommendation' => 'Prioriser les tâches critiques ou négocier une extension'];
        }

        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'overdue_tasks' => $overdueTasks,
            'unassigned_tasks' => $unassignedTasks,
            'velocity' => $velocity,
            'days_elapsed' => $daysElapsed,
            'prediction' => $prediction,
            'health_score' => $healthScore,
            'health_status' => $healthStatus,
            'warnings' => $warnings,
            'criticals' => $criticals,
            'risks' => $risks,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $projects
     * @return array<int, array<string, mixed>>
     */
    private function applyProjectFilters(array $projects, string $statusFilter, string $priorityFilter, string $search): array
    {
        if ($statusFilter === '' && $priorityFilter === '' && $search === '') {
            return $projects;
        }

        return array_filter($projects, static function (array $project) use ($statusFilter, $priorityFilter, $search): bool {
            if ($statusFilter !== '' && (string) ($project['status'] ?? '') !== $statusFilter) {
                return false;
            }

            if ($priorityFilter !== '' && (string) ($project['priority'] ?? '') !== $priorityFilter) {
                return false;
            }

            if ($search !== '') {
                $name = (string) ($project['name'] ?? '');
                $description = (string) ($project['description'] ?? '');

                if (stripos($name, $search) === false && stripos($description, $search) === false) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * @param array<int,array{employee_id:int,score:int,reason:string}> $aiSuggestions
     * @param array<int,array<string,mixed>> $candidates
     * @param array<string,mixed> $task
     * @return array<int,array<string,mixed>>
     */
    private function buildTaskSuggestions(array $aiSuggestions, array $candidates, array $task): array
    {
        $byEmployeeId = [];
        foreach ($candidates as $candidate) {
            $employeeId = (int) ($candidate['employee_id'] ?? 0);
            if ($employeeId <= 0) {
                continue;
            }

            $byEmployeeId[$employeeId] = [
                'employee_id' => $employeeId,
                'username' => (string) ($candidate['username'] ?? 'N/A'),
                'job_title' => (string) ($candidate['job_title'] ?? 'N/A'),
                'active_project_tasks' => (int) ($candidate['active_project_tasks'] ?? 0),
                'active_total_tasks' => (int) ($candidate['active_total_tasks'] ?? 0),
            ];
        }

        $result = [];
        foreach ($aiSuggestions as $item) {
            $employeeId = (int) $item['employee_id'];
            if (!isset($byEmployeeId[$employeeId])) {
                continue;
            }

            $result[] = array_merge($byEmployeeId[$employeeId], [
                'score' => (int) $item['score'],
                'reason' => (string) $item['reason'],
            ]);
        }

        if ($result !== []) {
            return array_slice($result, 0, 3);
        }

        $taskText = mb_strtolower(trim((string) (($task['title'] ?? '') . ' ' . ($task['description'] ?? ''))));
        $fallback = [];
        foreach ($byEmployeeId as $candidate) {
            $jobTitle = mb_strtolower((string) $candidate['job_title']);
            $jobMatch = ($taskText !== '' && $jobTitle !== '' && str_contains($taskText, $jobTitle)) ? 25 : 0;
            $loadPenalty = min(50, ((int) $candidate['active_total_tasks']) * 5);
            $score = max(1, 70 + $jobMatch - $loadPenalty);

            $fallback[] = array_merge($candidate, [
                'score' => $score,
                'reason' => 'Suggestion fallback: adaptation metier et charge actuelle.',
            ]);
        }

        usort($fallback, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        return array_slice($fallback, 0, 3);
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
