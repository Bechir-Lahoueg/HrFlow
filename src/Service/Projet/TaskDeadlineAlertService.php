<?php

namespace App\Service\Projet;

use App\Service\Shared\HrFlowMailer;
use Doctrine\DBAL\Connection;

final class TaskDeadlineAlertService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly HrFlowMailer $mailer,
    ) {
    }

    /**
     * @return array{rhProcessed:int,tasksFlagged:int,employeeEmailsSent:int,rhEmailsSent:int}
     */
    public function sendAlertsForAllRh(bool $dryRun = false): array
    {
        $rhIds = $this->connection->fetchFirstColumn('SELECT DISTINCT rh_id FROM projects WHERE rh_id IS NOT NULL');

        $summary = [
            'rhProcessed' => 0,
            'tasksFlagged' => 0,
            'employeeEmailsSent' => 0,
            'rhEmailsSent' => 0,
        ];

        foreach ($rhIds as $rhId) {
            $rhId = (int) $rhId;
            if ($rhId <= 0) {
                continue;
            }

            $result = $this->sendAlertsForRh($rhId, $dryRun);
            $summary['rhProcessed']++;
            $summary['tasksFlagged'] += $result['tasksFlagged'];
            $summary['employeeEmailsSent'] += $result['employeeEmailsSent'];
            $summary['rhEmailsSent'] += $result['rhEmailsSent'];
        }

        return $summary;
    }

    /**
     * @return array{tasksFlagged:int,employeeEmailsSent:int,rhEmailsSent:int}
     */
    public function sendAlertsForRh(int $rhId, bool $dryRun = false): array
    {
        if ($rhId <= 0) {
            return ['tasksFlagged' => 0, 'employeeEmailsSent' => 0, 'rhEmailsSent' => 0];
        }

        $tasks = $this->fetchAlertTasksForRh($rhId);
        if ($tasks === []) {
            return ['tasksFlagged' => 0, 'employeeEmailsSent' => 0, 'rhEmailsSent' => 0];
        }

        $employeeEmailsSent = $this->sendEmployeeAlerts($tasks, $dryRun);
        $rhEmailsSent = $this->sendRhDigest($rhId, $tasks, $dryRun);

        return [
            'tasksFlagged' => count($tasks),
            'employeeEmailsSent' => $employeeEmailsSent,
            'rhEmailsSent' => $rhEmailsSent,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchAlertTasksForRh(int $rhId): array
    {
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $tomorrow = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');

        $rows = $this->connection->fetchAllAssociative(
            "SELECT
                t.id,
                t.title,
                t.status,
                t.due_date,
                p.id AS project_id,
                p.name AS project_name,
                p.rh_id,
                e.id AS employee_id,
                e.email AS employee_email,
                CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, '')) AS employee_name,
                CASE
                    WHEN t.due_date < :today THEN 'overdue'
                    WHEN t.due_date = :tomorrow THEN 'due_tomorrow'
                    ELSE 'normal'
                END AS alert_type
            FROM project_tasks t
            INNER JOIN projects p ON p.id = t.project_id
            LEFT JOIN employees e ON e.id = t.assigned_to
            WHERE p.rh_id = :rhId
              AND t.status <> 'done'
              AND t.due_date IS NOT NULL
              AND (t.due_date < :today OR t.due_date = :tomorrow)
            ORDER BY t.due_date ASC, p.name ASC, t.title ASC",
            [
                'rhId' => $rhId,
                'today' => $today,
                'tomorrow' => $tomorrow,
            ]
        );

        foreach ($rows as &$row) {
            $row['employee_name'] = trim((string) ($row['employee_name'] ?? ''));
            if ($row['employee_name'] === '') {
                $row['employee_name'] = 'Non assigne';
            }
        }

        return $rows;
    }

    /**
     * @param array<int,array<string,mixed>> $tasks
     */
    private function sendEmployeeAlerts(array $tasks, bool $dryRun): int
    {
        $byEmployee = [];
        foreach ($tasks as $task) {
            $employeeId = (int) ($task['employee_id'] ?? 0);
            $email = trim((string) ($task['employee_email'] ?? ''));
            if ($employeeId <= 0 || $email === '') {
                continue;
            }

            if (!isset($byEmployee[$employeeId])) {
                $byEmployee[$employeeId] = [
                    'email' => $email,
                    'name' => (string) ($task['employee_name'] ?? 'Employe'),
                    'tasks' => [],
                ];
            }

            $byEmployee[$employeeId]['tasks'][] = $task;
        }

        $sent = 0;
        foreach ($byEmployee as $employeeData) {
            if (!$dryRun) {
                $this->mailer->sendProjectTaskAlertToEmployee(
                    (string) $employeeData['email'],
                    (string) $employeeData['name'],
                    (array) $employeeData['tasks']
                );
            }
            $sent++;
        }

        return $sent;
    }

    /**
     * @param array<int,array<string,mixed>> $tasks
     */
    private function sendRhDigest(int $rhId, array $tasks, bool $dryRun): int
    {
        $rh = $this->connection->fetchAssociative(
            'SELECT id, username, email FROM users WHERE id = ?',
            [$rhId]
        );

        if (!is_array($rh)) {
            return 0;
        }

        $email = trim((string) ($rh['email'] ?? ''));
        if ($email === '') {
            return 0;
        }

        $name = trim((string) ($rh['username'] ?? 'RH'));
        if ($name === '') {
            $name = 'RH';
        }

        if (!$dryRun) {
            $this->mailer->sendProjectTaskAlertDigestToRh($email, $name, $tasks);
        }
        return 1;
    }
}



