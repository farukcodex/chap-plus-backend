<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        $users = User::latest()->paginate($perPage);

        return $this->apiSuccess('Users retrieved successfully', $users);
    }

    public function show(string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return $this->apiError('User not found', 404);
        }

        return $this->apiSuccess('User retrieved successfully', $user);
    }
}
