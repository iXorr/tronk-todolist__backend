# TodoList API

REST API для приложения «Список задач» на Laravel 13.

## Стек

- **PHP 8.4+**, Laravel 13, Eloquent ORM.
- **База данных**: MySQL 8.4 (в Docker) или SQLite (для тестов).
- **Авторизация**: Laravel Sanctum, Bearer-токены.
- **Документация**: OpenAPI через `darkaonline/l5-swagger` (Swagger UI).

## Аккаунты по умолчанию

Сидер создаёт одного администратора и трёх обычных пользователей.
Пароль у всех — `password`.

| Email                | Роль  |
|---------------------|-------|
| `admin@example.com` | admin |
| `user1@example.com` | user  |
| `user2@example.com` | user  |
| `user3@example.com` | user  |

## Флаги прав в ответе

Каждая задача в ответе содержит два булевых поля — `can_update` и
`can_delete` — чтобы фронтенд мог скрывать кнопки редактирования и
удаления без дублирования бизнес-логики:

```json
{
  "data": {
    "id": 1,
    "title": "...",
    "can_update": true,
    "can_delete": false
  }
}
```

Админу эти флаги всегда `true` для любой задачи. Обычному
пользователю — `true` только для собственных.

## Формат ошибок

Laravel отдаёт ошибки в стандартном JSON-формате:

```json
{
  "message": "Краткое описание",
  "errors": { "field": ["Текст ошибки для этого поля"] }
}
```

## Документация (Swagger/OpenAPI)

Генерируется из PHP-атрибутов `#[OA\*]` на контроллерах через
`darkaonline/l5-swagger`. UI доступен по адресу
`http://localhost/api/documentation`.

## Тесты

Тесты гоняются на SQLite `:memory:` (см. `phpunit.xml`),
независимо от MySQL-контейнера. 27 feature-тестов покрывают auth и
CRUD задач.

```bash
docker compose exec backend php artisan test
```

## Линтинг

`laravel/pint` (PHP CS Fixer-style), конфиг — `pint.json`.

```bash
docker compose exec backend vendor/bin/pint --test
```