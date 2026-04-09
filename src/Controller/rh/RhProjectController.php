<?php

namespace App\Controller\rh;

use App\Service\ProjectService;
use App\Service\ProjectTaskService;
use App\Service\ProjectCollaboratorService;
use App\Service\ProjectMilestoneService;
use App\Service\ProjectUpdateService;
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
    ) {}

    // ═══════════════════════════════════════════════════════════════
    // LISTE DES PROJETS
    // ═══════════════════════════════════════════════════════════════

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        $rhId = $user->getId();

        $projects = $this->projectService->getProjectsWithDetails($rhId);
        $stats = $this->projectService->getProjectStats($rhId);

        // Filtres
        $statusFilter = $request->query->get('status', '');
        $priorityFilter = $request->query->get('priority', '');
        $search = $request->query->get('search', '');

        if ($statusFilter || $priorityFilter || $search) {
            $projects = array_filter($projects, function ($p) use ($statusFilter, $priorityFilter, $search) {
                if ($statusFilter && $p['status'] !== $statusFilter) return false;
                if ($priorityFilter && $p['priority'] !== $priorityFilter) return false;
                if ($search && stripos($p['name'], $search) === false && stripos($p['description'] ?? '', $search) === false) return false;
                return true;
            });
        }

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

    // ═══════════════════════════════════════════════════════════════
    // DÉTAILS D'UN PROJET
    // ═══════════════════════════════════════════════════════════════

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
    // --- AJOUT : On récupère le RH connecté ---
        $user = $this->getUser();
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

        // Organiser les tâches pour le Kanban
        $tasksByStatus = [
            'todo' => [],
            'in_progress' => [],
            'review' => [],
            'done' => [],
        ];
        foreach ($tasks as $task) {
            $status = $task['status'] ?? 'todo';
            if (isset($tasksByStatus[$status])) {
                $tasksByStatus[$status][] = $task;
            }
        }

        // Calculer les analytics
        $analytics = $this->calculateAnalytics($project, $tasks, $team, $milestones);

        // Employés disponibles pour ajout
        $availableEmployees = $this->collaboratorService->getAvailableEmployees($id, $rhId);

        return $this->render('DashboardHr/Project/show.html.twig', [
            'project' => $project,
            'tasks' => $tasks,
            'tasksByStatus' => $tasksByStatus,
            'team' => $team,
            'milestones' => $milestones,
            'updates' => $updates,
            'milestoneStats' => $milestoneStats,
            'analytics' => $analytics,
            'availableEmployees' => $availableEmployees,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // CRÉER UN PROJET
    // ═══════════════════════════════════════════════════════════════

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $user = $this->getUser();

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
                ]);
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

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request): Response
    {
        $project = $this->projectService->getProjectById($id);
        if (!$project) {
            throw $this->createNotFoundException('Projet non trouvé');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $this->projectService->updateProject($id, $data);
            $this->addFlash('success', 'Projet modifié avec succès !');
            return $this->redirectToRoute('rh_project_show', ['id' => $id]);
        }

        return $this->render('DashboardHr/Project/edit.html.twig', [
            'project' => $project,
            'statuses' => ['planning', 'in_progress', 'on_hold', 'completed', 'cancelled'],
            'priorities' => ['low', 'medium', 'high', 'critical'],
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
        $user = $this->getUser();
        $data = $request->request->all();
        $data['project_id'] = $id;

        $this->taskService->createTask($data);
        $this->updateService->logTaskCreated($id, $user->getId(), $data['title']);
        $this->addFlash('success', 'Tâche créée !');
        return $this->redirectToRoute('rh_project_show', ['id' => $id, '_fragment' => 'kanban']);
    }

    #[Route('/{id}/tasks/{taskId}/edit', name: 'task_edit', requirements: ['id' => '\d+', 'taskId' => '\d+'], methods: ['POST'])]
    public function editTask(int $id, int $taskId, Request $request): Response
    {
        $data = $request->request->all();
        $this->taskService->updateTask($taskId, $data);
        $this->addFlash('success', 'Tâche modifiée !');
        return $this->redirectToRoute('rh_project_show', ['id' => $id, '_fragment' => 'kanban']);
    }

    #[Route('/{id}/tasks/{taskId}/move', name: 'task_move', requirements: ['id' => '\d+', 'taskId' => '\d+'], methods: ['POST'])]
    public function moveTask(int $id, int $taskId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        $newStatus = $request->request->get('status');
        $task = $this->taskService->getTaskById($taskId);

        if ($task && $newStatus) {
            $this->taskService->updateTaskStatus($taskId, $newStatus);
            if ($newStatus === 'done') {
                $this->updateService->logTaskCompleted($id, $user->getId(), $task['title']);
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

    // ═══════════════════════════════════════════════════════════════
    // GESTION DE L'ÉQUIPE
    // ═══════════════════════════════════════════════════════════════

    #[Route('/{id}/team/add', name: 'team_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addTeamMember(int $id, Request $request): Response
    {
        $user = $this->getUser();
        $data = $request->request->all();
        $data['project_id'] = $id;

        $this->collaboratorService->addCollaborator($data);
        $this->addFlash('success', 'Membre ajouté à l\'équipe !');
        return $this->redirectToRoute('rh_project_show', ['id' => $id, '_fragment' => 'team']);
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
        $user = $this->getUser();
        $milestone = $this->milestoneService->getMilestoneById($milestoneId);
        $this->milestoneService->markAsCompleted($milestoneId);
        if ($milestone) {
            $this->updateService->logMilestoneCompleted($id, $user->getId(), $milestone['name']);
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
        $user = $this->getUser();
        $content = $request->request->get('content');

        if ($content) {
            $this->updateService->createUpdate([
                'project_id' => $id,
                'user_id' => $user->getId(),
                'update_type' => 'comment',
                'title' => 'Commentaire',
                'content' => $content,
            ]);
            $this->addFlash('success', 'Commentaire publié !');
        }

        return $this->redirectToRoute('rh_project_show', ['id' => $id, '_fragment' => 'activity']);
    }

    // ═══════════════════════════════════════════════════════════════
    // ANALYTICS HELPER
    // ═══════════════════════════════════════════════════════════════

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
            $daysDiff = $predictedEnd > $plannedEnd ? -$diff->days : $diff->days;

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
}