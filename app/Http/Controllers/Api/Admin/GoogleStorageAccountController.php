<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GoogleStorageAccountStoreRequest;
use App\Http\Requests\Admin\GoogleStorageAccountUpdateRequest;
use App\Http\Resources\Admin\GoogleStorageAccountResource;
use App\Models\GoogleStorageAccount;
use App\Models\User;
use App\Services\Google\GoogleOAuthService;
use App\Services\Google\GoogleStorageAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoogleStorageAccountController extends Controller
{
    public function __construct(
        private readonly GoogleOAuthService $oauthService,
        private readonly GoogleStorageAccountService $accountService,
    ) {}

    public function index(): JsonResponse
    {
        $accounts = GoogleStorageAccount::query()
            ->with('connectedBy:id,name')
            ->withCount('courses')
            ->latest()
            ->get();

        return $this->successResponse(
            GoogleStorageAccountResource::collection($accounts),
            'Google storage accounts retrieved successfully.'
        );
    }

    public function store(GoogleStorageAccountStoreRequest $request): JsonResponse
    {
        $account = GoogleStorageAccount::create([
            'name' => $request->validated('name'),
            'status' => GoogleStorageAccount::STATUS_PENDING,
            'connected_by' => $request->user()->id,
        ]);

        return $this->successResponse(
            new GoogleStorageAccountResource($account),
            'Google storage account created successfully.',
            201
        );
    }

    public function update(GoogleStorageAccountUpdateRequest $request, GoogleStorageAccount $googleStorageAccount): JsonResponse
    {
        $googleStorageAccount->update($request->validated());

        return $this->successResponse(
            new GoogleStorageAccountResource($googleStorageAccount->fresh()),
            'Google storage account updated successfully.'
        );
    }

    public function destroy(GoogleStorageAccount $googleStorageAccount): JsonResponse
    {
        if ($googleStorageAccount->courses()->exists()) {
            return $this->errorResponse(
                'Cannot delete account while courses are still assigned to it.',
                409
            );
        }

        $googleStorageAccount->delete();

        return $this->successResponse(null, 'Google storage account deleted successfully.');
    }

    public function connect(Request $request, GoogleStorageAccount $googleStorageAccount): JsonResponse
    {
        $authUrl = $this->oauthService->buildAuthUrl(
            $googleStorageAccount->id,
            $request->user()->id
        );

        return $this->successResponse([
            'auth_url' => $authUrl,
        ], 'Google OAuth URL generated successfully.');
    }

    public function callback(Request $request)
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $redirectBase = "{$frontendUrl}/admin/google-storage-accounts";

        if (! $request->filled('code') || ! $request->filled('state')) {
            return redirect("{$redirectBase}?error=missing_oauth_params");
        }

        try {
            $state = $this->oauthService->decodeState($request->string('state')->toString());
            $adminUser = User::find($state['admin_user_id']);

            if (! $adminUser || ! $adminUser->hasRole('admin')) {
                return redirect("{$redirectBase}?error=invalid_oauth_state");
            }

            $account = GoogleStorageAccount::findOrFail($state['account_id']);
            $token = $this->oauthService->exchangeCode($request->string('code')->toString());

            $account->update([
                'access_token' => $token['access_token'] ?? null,
                'refresh_token' => $token['refresh_token'] ?? $account->refresh_token,
                'token_expires_at' => isset($token['expires_in'])
                    ? now()->addSeconds((int) $token['expires_in'])
                    : now()->addHour(),
                'scope' => $token['scope'] ?? null,
                'status' => GoogleStorageAccount::STATUS_CONNECTED,
                'connected_by' => $adminUser->id,
                'last_error' => null,
            ]);

            $this->accountService->testConnection($account->fresh());

            return redirect("{$redirectBase}?connected=1");
        } catch (\Throwable) {
            return redirect("{$redirectBase}?error=oauth_failed");
        }
    }

    public function disconnect(GoogleStorageAccount $googleStorageAccount): JsonResponse
    {
        $account = $this->accountService->disconnect($googleStorageAccount);

        return $this->successResponse(
            new GoogleStorageAccountResource($account),
            'Google storage account disconnected successfully.'
        );
    }

    public function testConnection(GoogleStorageAccount $googleStorageAccount): JsonResponse
    {
        $account = $this->accountService->testConnection($googleStorageAccount);

        return $this->successResponse(
            new GoogleStorageAccountResource($account),
            'Google storage account connection verified successfully.'
        );
    }
}
