<?php

namespace App\Service\Projet;

use App\Repository\Projet\ProjectChatRoomRepository;

final class ProjectChatRoomService
{
    public function __construct(private readonly ProjectChatRoomRepository $chatRoomRepository)
    {
    }

    public function getRoomIdByProject(int $projectId): ?string
    {
        try {
            return $this->chatRoomRepository->getRoomIdByProject($projectId);
        } catch (\Throwable) {
            return null;
        }
    }

    public function saveRoomId(int $projectId, string $roomId): void
    {
        $this->chatRoomRepository->saveRoomId($projectId, $roomId);
    }
}
