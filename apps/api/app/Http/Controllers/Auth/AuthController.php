<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new player: create the user + their profile, then start a session.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create($request->safe()->only('name', 'email', 'password'));
            $user->playerProfile()->create([]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return UserResource::make($user->load('playerProfile'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Authenticate an existing player and start a session.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return UserResource::make($request->user()->load('playerProfile'))->response();
    }

    /**
     * Return the authenticated player.
     */
    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user()->load('playerProfile'));
    }

    /**
     * End the current session.
     */
    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
