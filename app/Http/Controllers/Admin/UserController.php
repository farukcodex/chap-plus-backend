<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminUserDetailResource;
use App\Http\Resources\Admin\AdminUserResource;
use App\Models\BusBooking;
use App\Models\HotelBooking;
use App\Models\Order;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\Wallet;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of registered users (Accounts -> Users)
     * Supports search by name/email/phone, status filter, and sanitized pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $role = $request->input('role', 'USER');
        $query = User::query();

        if ($role && strtolower($role) !== 'all') {
            $query->role($role);
        }

        $query->with(['roles:id,name', 'userProfile']);

        // Search by name, email, or profile phone number
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhereHas('userProfile', fn ($p) => $p->where('phone_number', 'like', "%{$search}%"));
            });
        }

        // Filter by account status (active, blocked)
        if ($request->filled('status')) {
            $status = strtolower($request->input('status'));
            if ($status === 'blocked') {
                $query->where('is_blocked', true);
            } elseif ($status === 'active') {
                $query->where('is_blocked', false);
            }
        }

        $perPage = max(1, (int) $request->input('per_page', 10));
        $users = $query->latest()->paginate($perPage);
        $users->through(fn ($user) => (new AdminUserResource($user))->resolve());

        return $this->apiSuccess('Users retrieved successfully', $users);
    }

    /**
     * Display detailed user information (for Eye action modal/view)
     */
    public function show(string $id): JsonResponse
    {
        $user = User::with(['roles:id,name', 'userProfile'])->find($id);

        if (!$user) {
            return $this->apiError('User not found', 404);
        }

        // Attach related activity & wallet
        $user->total_orders = Order::where('user_id', $user->id)->count();
        $user->total_bus_bookings = BusBooking::where('user_id', $user->id)->count();
        $user->total_hotel_bookings = HotelBooking::where('user_id', $user->id)->count();
        $user->wallet = Wallet::where('user_id', $user->id)->first();
        $user->addresses = UserAddress::where('user_id', $user->id)->get(['id', 'title', 'address_text', 'phone_number']);

        return $this->apiSuccess('User details retrieved successfully', new AdminUserDetailResource($user));
    }

    /**
     * Toggle or set the block status of a user (for Block/Slash icon action)
     * When blocking, all active Sanctum tokens are revoked.
     */
    public function toggleBlock(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return $this->apiError('User not found', 404);
        }

        if ($user->hasRole('ADMIN')) {
            return $this->apiError('Admin accounts cannot be blocked', 403);
        }

        $newStatus = $request->has('is_blocked')
            ? (bool) $request->input('is_blocked')
            : !$user->is_blocked;

        $user->update(['is_blocked' => $newStatus]);

        if ($newStatus) {
            $user->tokens()->delete();
        }

        $message = $newStatus ? 'User account has been blocked' : 'User account has been unblocked';

        return $this->apiSuccess($message, new AdminUserResource($user->fresh(['roles:id,name', 'userProfile'])));
    }
}
