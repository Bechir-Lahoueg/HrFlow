<?php

namespace App\Service\Projet;

use Doctrine\DBAL\Connection;

final class ProjectChatRoomService
{
	public function __construct(private readonly Connection $connection)
	{
	}

	public function getRoomIdByProject(int $projectId): ?string
	{
		try {
			$roomId = $this->connection->fetchOne(
				'SELECT room_id FROM project_chat_rooms WHERE project_id = ?',
				[$projectId]
			);

			return is_string($roomId) && $roomId !== '' ? $roomId : null;
		} catch (\Throwable) {
			return null;
		}
	}

	public function saveRoomId(int $projectId, string $roomId): void
	{
		$exists = $this->connection->fetchOne(
			'SELECT id FROM project_chat_rooms WHERE project_id = ?',
			[$projectId]
		);

		if ($exists) {
			$this->connection->update('project_chat_rooms', [
				'room_id' => $roomId,
				'updated_at' => date('Y-m-d H:i:s'),
			], ['project_id' => $projectId]);

			return;
		}

		$this->connection->insert('project_chat_rooms', [
			'project_id' => $projectId,
			'room_id' => $roomId,
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s'),
		]);
	}
}

