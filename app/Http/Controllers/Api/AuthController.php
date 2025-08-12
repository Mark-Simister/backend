<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ApiUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    /**
     * Register a new user (API only).
     */
    public function register(Request $request)
{
    $validator = Validator::make($request->all(), [
        'name'     => 'required|string|max:255',
        'email'    => 'required|string|email|unique:users,email',
        'password' => 'required|string|min:6',
        'phone'    => 'nullable|string',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status'  => false,
            'message' => 'Validation failed.',
            'errors'  => $validator->errors()
        ], 422);
    }

    // Ensure role exists for api guard
    if (!Role::where('name', 'user')->where('guard_name', 'api')->exists()) {
        Role::create(['name' => 'user', 'guard_name' => 'api']);
    }

    $user = ApiUser::create([
        'name'        => $request->name,
        'email'       => $request->email,
        'password'    => Hash::make($request->password),
        'phone'       => $request->phone,
        'role'        => 'user', // optional reference
        'is_verified' => false,
    ]);

    // Assign role to this user
    $user->assignRole('user');

    // Generate JWT token
    $token = JWTAuth::fromUser($user);

    return response()->json([
        'status'  => true,
        'message' => 'User registered successfully.',
        'data'    => [
            'token' => $token,
            'user'  => $user
        ]
    ], 201);
}

    /**
     * Authenticate a user and issue a JWT.
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid credentials.'
            ], 401);
        }

        $user = auth('api')->user();

        // Restrict API access to role "user"
        if (!$user->hasRole('user')) {
            return response()->json([
                'status'  => false,
                'message' => 'Access denied. Insufficient permissions.'
            ], 403);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Login successful.',
            'data'    => [
                'token' => $token,
                'user'  => $user
            ]
        ], 200);
    }

    /**
     * Logout user (invalidate token).
     */
    public function logout()
    {
        auth('api')->logout();

        return response()->json([
            'status'  => true,
            'message' => 'Successfully logged out.'
        ], 200);
    }

    /**
     * Get authenticated user details.
     */
    public function me()
    {
        return response()->json([
            'status'  => true,
            'message' => 'User profile retrieved successfully.',
            'data'    => auth('api')->user()
        ], 200);
    }
}