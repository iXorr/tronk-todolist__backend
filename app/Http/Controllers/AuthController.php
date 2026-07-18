<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'TODO API',
    description: 'REST API для приложения «Список задач». Авторизация — Bearer-токены (Laravel Sanctum).',
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    description: 'Bearer-токен Laravel Sanctum. Возвращается из POST /api/auth/login.',
)]
class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/auth/login',
        summary: 'Авторизация пользователя и выдача токена',
        tags: ['Авторизация'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@example.com', description: 'Email пользователя'),
                    new OA\Property(property: 'password', type: 'string', example: 'password', description: 'Пароль'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Токен выдан',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'token', type: 'string', example: '1|abcdef...', description: 'Bearer-токен'),
                        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                    ],
                )),
            new OA\Response(response: 422, description: 'Ошибка валидации',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'These credentials do not match our records.'),
                        new OA\Property(property: 'errors', type: 'object',
                            properties: [
                                new OA\Property(property: 'email', type: 'array', items: new OA\Items(type: 'string', example: 'These credentials do not match our records.')),
                            ],
                        ),
                    ],
                )),
        ],
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    #[OA\Post(
        path: '/api/auth/logout',
        summary: 'Отзыв текущего токена',
        tags: ['Авторизация'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 204, description: 'Токен отозван'),
            new OA\Response(response: 401, description: 'Не авторизован',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')],
                )),
        ],
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(null, 204);
    }

    #[OA\Get(
        path: '/api/user',
        summary: 'Получить текущего пользователя',
        tags: ['Авторизация'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Текущий пользователь',
                content: new OA\JsonContent(ref: '#/components/schemas/User')),
            new OA\Response(response: 401, description: 'Не авторизован',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')],
                )),
        ],
    )]
    public function user(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}