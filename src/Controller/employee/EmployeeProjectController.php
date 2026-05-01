<?php

namespace App\Controller\employee;

use App\Security\DbUser;
use App\Service\Projet\ProjectService;
use App\Service\Projet\ProjectTaskService;
use App\Service\Projet\ProjectCollaboratorService;
use App\Service\Projet\ProjectUpdateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/employee/projects', name: 'employee_project_')]
class EmployeeProjectController extends AbstractController
{
    public function __construct(
        private readonly ProjectService $projectService,
        private readonly ProjectTaskService $taskService,
        private readonly ProjectCollaboratorService $collaboratorService,
        private readonly ProjectUpdateService $updateService,
    ) {}

    // ═══════════════════════════════════════════════════════════════
    // MES PROJETS
    // ═══════════════════════════════════════════════════════════════

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $user = $this->getDbUser();
        $employeeId = $user->getId();

        $projects = $this->collaboratorService->getProjectsByEmployee($employeeId);
        $taskStats = $this->taskService->getEmployeeTaskStats($employeeId);
        $myTasks = $this->taskService->getTasksByEmployee($employeeId);

        // Filtre statut tâches
        $statusFilter = $request->query->get('status', '');
        if ($statusFilter) {
            $myTasks = array_filter($myTasks, fn($t) => $t['status'] === $statusFilter);
        }

        // Total heures travaillées
        $totalHours = 0;
        foreach ($projects as $project) {
            $collab = $this->getMyCollaboration($project['id'], $employeeId);
            if ($collab) $totalHours += (int)($collab['worked_hours'] ?? 0);
        }

        return $this->render('DashboardEmployee/Project/index.html.twig', [
            'projects' => $projects,
            'myTasks' => array_values($myTasks),
            'taskStats' => $taskStats,
            'totalHours' => $totalHours,
            'statusFilter' => $statusFilter,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // KANBAN D'UN PROJET
    // ═══════════════════════════════════════════════════════════════

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $user = $this->getDbUser();
        $employeeId = $user->getId();

        $project = $this->projectService->getProjectById($id);
        if (!$project) {
            throw $this->createNotFoundException('Projet non trouvé');
        }

        // Vérifier que l'employé est bien dans ce projet
        if (!$this->collaboratorService->isCollaborator($id, $employeeId)) {
            $this->addFlash('error', 'Vous n\'êtes pas membre de ce projet.');
            return $this->redirectToRoute('employee_project_index');
        }

        // Récupérer uniquement MES tâches sur ce projet
        $allMyTasks = $this->taskService->getTasksByEmployee($employeeId);
        $myTasks = array_filter($allMyTasks, fn($t) => (int)$t['project_id'] === $id);

        $tasksByStatus = ['todo' => [], 'in_progress' => [], 'review' => [], 'done' => []];
        foreach ($myTasks as $task) {
            $status = $task['status'] ?? 'todo';
            if (isset($tasksByStatus[$status])) {
                $tasksByStatus[$status][] = $task;
            }
        }

        $myCollaboration = $this->getMyCollaboration($id, $employeeId);
        $updates = $this->updateService->getUpdatesByProject($id, 15);

        $totalMyTasks = count($myTasks);
        $doneMyTasks = count(array_filter($myTasks, fn($t) => $t['status'] === 'done'));

        return $this->render('DashboardEmployee/Project/Kanban.html.twig', [
            'project' => $project,
            'tasksByStatus' => $tasksByStatus,
            'myTasks' => array_values($myTasks),
            'myCollaboration' => $myCollaboration,
            'updates' => $updates,
            'totalMyTasks' => $totalMyTasks,
            'doneMyTasks' => $doneMyTasks,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // DÉPLACER UNE TÂCHE (AJAX)
    // ═══════════════════════════════════════════════════════════════

    #[Route('/{id}/tasks/{taskId}/move', name: 'task_move', requirements: ['id' => '\d+', 'taskId' => '\d+'], methods: ['POST'])]
    public function moveTask(int $id, int $taskId, Request $request): JsonResponse
    {
        $user = $this->getDbUser();
        $employeeId = $user->getId();

        $newStatus = $request->request->get('status');
        $task = $this->taskService->getTaskById($taskId);

        // Sécurité: vérifier que la tâche appartient à cet employé
        if (!$task || (int)($task['assigned_to'] ?? 0) !== $employeeId) {
            return new JsonResponse(['success' => false, 'message' => 'Non autorisé'], 403);
        }

        $this->taskService->updateTaskStatus($taskId, $newStatus);

        if ($newStatus === 'done') {
            $this->updateService->logTaskCompleted($id, $employeeId, $task['title']);
        } else {
            $this->updateService->createUpdate([
                'project_id' => $id,
                'user_id' => $employeeId,
                'update_type' => 'task',
                'title' => 'Tâche déplacée',
                'content' => $task['title'] . ' → ' . $newStatus,
            ]);
        }

        $this->projectService->updateCompletionRate($id);
        return new JsonResponse(['success' => true]);
    }

    // ═══════════════════════════════════════════════════════════════
    // LOGGER DES HEURES
    // ═══════════════════════════════════════════════════════════════

    #[Route('/{id}/tasks/{taskId}/log-hours', name: 'task_log_hours', requirements: ['id' => '\d+', 'taskId' => '\d+'], methods: ['POST'])]
    public function logHours(int $id, int $taskId, Request $request): Response
    {
        $user = $this->getDbUser();
        $employeeId = $user->getId();

        $hours = (int) $request->request->get('hours', 0);
        $task = $this->taskService->getTaskById($taskId);

        if ($task && $hours > 0 && (int)($task['assigned_to'] ?? 0) === $employeeId) {
            $this->taskService->logHours($taskId, $hours);

            // Logger aussi pour le collaborateur
            $collab = $this->getMyCollaboration($id, $employeeId);
            if ($collab) {
                $this->collaboratorService->logWorkedHours($collab['id'], $hours);
            }

            $this->updateService->createUpdate([
                'project_id' => $id,
                'user_id' => $employeeId,
                'update_type' => 'task',
                'title' => 'Heures loggées',
                'content' => $hours . 'h ajoutées sur: ' . $task['title'],
            ]);

            $this->addFlash('success', $hours . ' heure(s) ajoutée(s) !');
        } else {
            $this->addFlash('error', 'Données invalides.');
        }

        return $this->redirectToRoute('employee_project_show', ['id' => $id]);
    }

    // ═══════════════════════════════════════════════════════════════
    // COMMENTER
    // ═══════════════════════════════════════════════════════════════

    #[Route('/{id}/comment', name: 'comment', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addComment(int $id, Request $request): Response
    {
        $user = $this->getDbUser();
        $employeeId = $user->getId();

        $content = $request->request->get('content');
        if ($content && $this->collaboratorService->isCollaborator($id, $employeeId)) {
            $this->updateService->createUpdate([
                'project_id' => $id,
                'user_id' => $employeeId,
                'update_type' => 'comment',
                'title' => 'Commentaire',
                'content' => $content,
            ]);
            $this->addFlash('success', 'Commentaire publié !');
        }

        return $this->redirectToRoute('employee_project_show', ['id' => $id, '_fragment' => 'activity']);
    }

    // ═══════════════════════════════════════════════════════════════
    // HELPER
    // ═══════════════════════════════════════════════════════════════

    /** @return array<string, mixed>|null */
    private function getMyCollaboration(int $projectId, int $employeeId): ?array
    {
        $collabs = $this->collaboratorService->getCollaboratorsByProject($projectId);
        foreach ($collabs as $c) {
            if ((int)$c['employee_id'] === $employeeId) return $c;
        }
        return null;
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

