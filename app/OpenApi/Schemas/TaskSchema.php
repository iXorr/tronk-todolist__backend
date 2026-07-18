<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Task')]
class TaskSchema
{
    #[OA\Property(property: 'id', type: 'integer', example: 1, description: 'Идентификатор задачи')]
    public int $id;

    #[OA\Property(property: 'user_id', type: 'integer', example: 1, description: 'Владелец задачи')]
    public int $user_id;

    #[OA\Property(property: 'title', type: 'string', example: 'Купить молоко', description: 'Заголовок (3-255 символов)')]
    public string $title;

    #[OA\Property(property: 'description', type: 'string', nullable: true, example: '2 литра овсяного', description: 'Описание')]
    public ?string $description;

    #[OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true, example: '2026-08-01', description: 'Дедлайн')]
    public ?string $due_date;

    #[OA\Property(property: 'status', type: 'string',
        enum: ['pending', 'in_progress', 'completed'],
        example: 'pending',
        description: 'Статус: pending — ожидающая, in_progress — в работе, completed — завершена')]
    public string $status;

    #[OA\Property(property: 'created_at', type: 'string', format: 'date-time', description: 'Дата создания')]
    public string $created_at;

    #[OA\Property(property: 'updated_at', type: 'string', format: 'date-time', description: 'Дата обновления')]
    public string $updated_at;

    #[OA\Property(property: 'can_update', type: 'boolean', example: true, description: 'Может ли текущий пользователь редактировать')]
    public bool $can_update;

    #[OA\Property(property: 'can_delete', type: 'boolean', example: true, description: 'Может ли текущий пользователь удалить')]
    public bool $can_delete;
}