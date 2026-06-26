<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ApiController extends Controller
{
    protected function success(array|object $data = [], string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function failure(string $message = 'Error', int $status = 400, array|object|null $errors = null): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    protected function gate(string $permission): ?\Illuminate\Http\JsonResponse
    {
        $user = request()->user();

        if (! $user?->hasPermission($permission)) {
            return $this->failure('You do not have permission to perform this action.', 403);
        }

        return null;
    }

    protected function superAdminOnly(): ?JsonResponse
    {
        $user = request()->user();

        if (! $user?->hasRole('super_admin')) {
            return $this->failure('This action is restricted to Super Administrators.', 403);
        }

        return null;
    }
}
