<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class TaskController extends Controller
{
    #[OA\Get(
        path: '/api/tasks',
        summary: 'Список задач с фильтрами, сортировкой и пагинацией',
        description: 'Админ видит все задачи; обычный пользователь — только свои.',
        tags: ['Задачи'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false,
                schema: new OA\Schema(type: 'string', enum: ['pending', 'in_progress', 'completed']),
                description: 'Фильтр по статусу'),
            new OA\Parameter(name: 'search', in: 'query', required: false,
                schema: new OA\Schema(type: 'string'), description: 'Поиск по заголовку (подстрока)'),
            new OA\Parameter(name: 'sort', in: 'query', required: false,
                schema: new OA\Schema(type: 'string', enum: ['due_date', 'status', 'created_at']),
                description: 'Поле сортировки'),
            new OA\Parameter(name: 'order', in: 'query', required: false,
                schema: new OA\Schema(type: 'string', enum: ['asc', 'desc']),
                description: 'Направление сортировки'),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1),
                description: 'Номер страницы'),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15),
                description: 'Размер страницы'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Пагинированный список задач',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Task')),
                        new OA\Property(property: 'links', type: 'object', description: 'Ссылки пагинации'),
                        new OA\Property(property: 'meta', type: 'object', description: 'Метаданные пагинации'),
                    ],
                )),
            new OA\Response(response: 401, description: 'Не авторизован',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')],
                )),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $tasks = Task::query()
            ->when(! $user->is_admin, fn ($q) => $q->where('user_id', $user->id))
            ->search($request->string('search')->toString())
            ->filterStatus($request->string('status')->toString())
            ->applySort($request->string('sort')->toString(), $request->string('order')->toString())
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return TaskResource::collection($tasks);
    }

    #[OA\Post(
        path: '/api/tasks',
        summary: 'Создать задачу для текущего пользователя',
        tags: ['Задачи'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', minLength: 3, maxLength: 255, example: 'Купить молоко', description: 'Заголовок (3-255 символов)'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: '2 литра овсяного', description: 'Описание'),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true, example: '2026-08-01', description: 'Дедлайн (YYYY-MM-DD)'),
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'in_progress', 'completed'], example: 'pending', description: 'Статус'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Задача создана',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Task')],
                )),
            new OA\Response(response: 422, description: 'Ошибка валидации',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'The title field must be at least 3 characters.'),
                        new OA\Property(property: 'errors', type: 'object',
                            properties: [
                                new OA\Property(property: 'title', type: 'array', items: new OA\Items(type: 'string')),
                            ],
                        ),
                    ],
                )),
            new OA\Response(response: 401, description: 'Не авторизован',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')],
                )),
        ],
    )]
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $request->user()->tasks()->create($request->validated());

        return (new TaskResource($task))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/tasks/{task}',
        summary: 'Получить одну задачу',
        description: 'Только владелец или админ может просматривать задачу.',
        tags: ['Задачи'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'ID задачи'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Задача',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Task')],
                )),
            new OA\Response(response: 403, description: 'Доступ запрещён',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')],
                )),
            new OA\Response(response: 404, description: 'Не найдено',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Not found.')],
                )),
            new OA\Response(response: 401, description: 'Не авторизован',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')],
                )),
        ],
    )]
    public function show(Request $request, Task $task): TaskResource
    {
        $this->authorize('view', $task);

        return new TaskResource($task);
    }

    #[OA\Put(
        path: '/api/tasks/{task}',
        summary: 'Обновить задачу (частично или полностью)',
        description: 'Только владелец или админ может редактировать задачу.',
        tags: ['Задачи'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'ID задачи'),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', minLength: 3, maxLength: 255, example: 'Купить молоко', description: 'Заголовок (3-255 символов)'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: '2 литра овсяного', description: 'Описание'),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true, example: '2026-08-01', description: 'Дедлайн (YYYY-MM-DD)'),
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'in_progress', 'completed'], example: 'completed', description: 'Статус'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Задача обновлена',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Task')],
                )),
            new OA\Response(response: 403, description: 'Доступ запрещён',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')],
                )),
            new OA\Response(response: 404, description: 'Не найдено',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Not found.')],
                )),
            new OA\Response(response: 422, description: 'Ошибка валидации',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'The title field must be at least 3 characters.'),
                        new OA\Property(property: 'errors', type: 'object'),
                    ],
                )),
            new OA\Response(response: 401, description: 'Не авторизован',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')],
                )),
        ],
    )]
    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $this->authorize('update', $task);

        $task->update($request->validated());

        return new TaskResource($task);
    }

    #[OA\Delete(
        path: '/api/tasks/{task}',
        summary: 'Удалить задачу',
        description: 'Только владелец или админ может удалить задачу.',
        tags: ['Задачи'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'ID задачи'),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Задача удалена'),
            new OA\Response(response: 403, description: 'Доступ запрещён',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.')],
                )),
            new OA\Response(response: 404, description: 'Не найдено',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Not found.')],
                )),
            new OA\Response(response: 401, description: 'Не авторизован',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')],
                )),
        ],
    )]
    public function destroy(Request $request, Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->json(null, 204);
    }
}