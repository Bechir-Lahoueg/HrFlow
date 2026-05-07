<?php

namespace App\Repository\Projet;

use App\Entity\Projet\ProjectChatRoom;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectChatRoom>
 */
class ProjectChatRoomRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectChatRoom::class);
    }

    public function getRoomIdByProject(int $projectId): ?string
    {
        $roomId = $this->getConnection()->fetchOne(
            'SELECT room_id FROM project_chat_rooms WHERE project_id = ?',
            [$projectId]
        );

        return is_string($roomId) && $roomId !== '' ? $roomId : null;
    }

    public function saveRoomId(int $projectId, string $roomId): void
    {
        $exists = $this->getConnection()->fetchOne(
            'SELECT id FROM project_chat_rooms WHERE project_id = ?',
            [$projectId]
        );

        if ($exists) {
            $this->getConnection()->update('project_chat_rooms', [
                'room_id' => $roomId,
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['project_id' => $projectId]);
            return;
        }

        $this->getConnection()->insert('project_chat_rooms', [
            'project_id' => $projectId,
            'room_id' => $roomId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function getConnection(): Connection
    {
        return $this->getEntityManager()->getConnection();
    }
}

