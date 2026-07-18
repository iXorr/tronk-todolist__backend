<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Список возвращает только свои задачи для обычного пользователя')]
    public function test_list_returns_only_own_tasks_for_regular_user(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->create();

        Task::factory()->for($user)->create(['title' => 'Моя задача']);
        Task::factory()->for($other)->create(['title' => 'Чужая задача']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/tasks');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Моя задача');
    }

    #[TestDox('Список возвращает все задачи для админа')]
    public function test_list_returns_all_tasks_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();

        Task::factory()->for($admin)->create(['title' => 'Задача админа']);
        Task::factory()->for($other)->create(['title' => 'Задача другого']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/tasks');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[TestDox('Список требует авторизации')]
    public function test_list_requires_authentication(): void
    {
        $response = $this->getJson('/api/tasks');

        $response->assertStatus(401);
    }

    #[TestDox('Создание задачи для авторизованного пользователя')]
    public function test_store_creates_task_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/tasks', [
            'title' => 'Купить молоко',
            'description' => '2 литра',
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => 'pending',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Купить молоко')
            ->assertJsonPath('data.user_id', $user->id);
        $this->assertDatabaseHas('tasks', ['title' => 'Купить молоко']);
    }

    #[TestDox('Создание валидирует длину заголовка')]
    public function test_store_validates_title_length(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/tasks', [
            'title' => 'ab',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('title');
    }

    #[TestDox('Создание валидирует enum статуса')]
    public function test_store_validates_status_enum(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/tasks', [
            'title' => 'Корректный заголовок',
            'status' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    #[TestDox('Show: владелец получает задачу')]
    public function test_show_returns_task_to_owner(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create();

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/tasks/{$task->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $task->id);
    }

    #[TestDox('Show: доступ запрещён для не-владельца')]
    public function test_show_forbidden_for_non_owner(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $task = Task::factory()->for($other)->create();

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/tasks/{$task->id}");

        $response->assertStatus(403);
    }

    #[TestDox('Show: админ видит любую задачу')]
    public function test_show_admin_can_see_any_task(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();
        $task = Task::factory()->for($other)->create();

        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/tasks/{$task->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $task->id);
    }

    #[TestDox('Show: несуществующая задача возвращает 404')]
    public function test_show_returns_404_for_missing_task(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/tasks/999999');

        $response->assertStatus(404);
    }

    #[TestDox('Update: владелец может редактировать')]
    public function test_update_owner_can_modify_task(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create(['status' => 'pending']);

        $response = $this->actingAs($user, 'sanctum')->putJson("/api/tasks/{$task->id}", [
            'status' => 'completed',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');
    }

    #[TestDox('Update: запрещён для не-владельца')]
    public function test_update_forbidden_for_non_owner(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $task = Task::factory()->for($other)->create();

        $response = $this->actingAs($user, 'sanctum')->putJson("/api/tasks/{$task->id}", [
            'status' => 'completed',
        ]);

        $response->assertStatus(403);
    }

    #[TestDox('Destroy: владелец может удалить')]
    public function test_destroy_owner_can_delete_task(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create();

        $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/tasks/{$task->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    #[TestDox('Destroy: запрещён для не-владельца')]
    public function test_destroy_forbidden_for_non_owner(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $task = Task::factory()->for($other)->create();

        $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/tasks/{$task->id}");

        $response->assertStatus(403);
    }

    #[TestDox('Фильтр по статусу')]
    public function test_filter_by_status(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->create(['status' => 'pending']);
        Task::factory()->for($user)->create(['status' => 'completed']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/tasks?status=completed');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'completed');
    }

    #[TestDox('Поиск по заголовку')]
    public function test_search_by_title(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->create(['title' => 'Купить молоко']);
        Task::factory()->for($user)->create(['title' => 'Гулять с собакой']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/tasks?search=молоко');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Купить молоко');
    }

    #[TestDox('Сортировка по дедлайну по возрастанию')]
    public function test_sort_by_due_date_ascending(): void
    {
        $user = User::factory()->create();
        $later = Task::factory()->for($user)->create(['due_date' => '2026-12-31']);
        $earlier = Task::factory()->for($user)->create(['due_date' => '2026-01-15']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/tasks?sort=due_date&order=asc');

        $response->assertStatus(200);
        $this->assertEquals($earlier->id, $response->json('data.0.id'));
    }

    #[TestDox('Метаданные пагинации')]
    public function test_pagination_meta(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->count(20)->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/tasks?per_page=5');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 20)
            ->assertJsonCount(5, 'data');
    }

    #[TestDox('Флаги can_update и can_delete для владельца')]
    public function test_can_update_and_can_delete_flags_for_owner(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create();

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/tasks/{$task->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.can_update', true)
            ->assertJsonPath('data.can_delete', true);
    }

    #[TestDox('Show: не-владелец получает 403 (can_* недоступны)')]
    public function test_non_owner_gets_403_and_no_can_flags(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $task = Task::factory()->for($admin)->create();

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/tasks/{$task->id}");

        $response->assertStatus(403);
    }
}