<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'User')]
class UserSchema
{
    #[OA\Property(property: 'id', type: 'integer', example: 1, description: 'Идентификатор пользователя')]
    public int $id;

    #[OA\Property(property: 'name', type: 'string', example: 'Алиса', description: 'Имя пользователя')]
    public string $name;

    #[OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@example.com', description: 'Email')]
    public string $email;

    #[OA\Property(property: 'is_admin', type: 'boolean', example: true, description: 'Является ли администратором')]
    public bool $is_admin;

    #[OA\Property(property: 'created_at', type: 'string', format: 'date-time', description: 'Дата создания')]
    public string $created_at;

    #[OA\Property(property: 'updated_at', type: 'string', format: 'date-time', description: 'Дата обновления')]
    public string $updated_at;
}