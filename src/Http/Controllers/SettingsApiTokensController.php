<?php

namespace Dcplibrary\Notices\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Settings API tokens controller.
 */
class SettingsApiTokensController extends Controller
{
    /**
     * Display API tokens management page.
     *
     * @return \Illuminate\View\View
     */
    public function apiTokens()
    {
        $user = auth()->user();

        // Check if user has Sanctum tokens available
        $tokens = null;
        if (method_exists($user, 'tokens')) {
            $tokens = $user->tokens()->orderBy('created_at', 'desc')->get();
        } elseif (class_exists(PersonalAccessToken::class)) {
            // Fallback: query tokens directly if user model doesn't have relationship
            $tokens = PersonalAccessToken::where('tokenable_type', get_class($user))
                ->where('tokenable_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get()
            ;
        }

        // Get new token from session if it exists
        $newToken = session('new_token');

        return view('notices::settings.api-tokens', [
            'tokens'   => $tokens,
            'newToken' => $newToken,
        ]);
    }

    /**
     * Create a new API token.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function createApiToken(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'abilities' => 'nullable|array',
        ]);

        $user = auth()->user();

        // Check if user can create tokens
        if (!method_exists($user, 'createToken')) {
            return redirect()
                ->route('notices.settings.api-tokens')
                ->with('error', 'API token creation is not available. Please ensure Laravel Sanctum is properly configured.')
            ;
        }

        $abilities = $data['abilities'] ?? ['*'];

        // Create token
        $token = $user->createToken($data['name'], $abilities);

        return redirect()
            ->route('notices.settings.api-tokens')
            ->with('new_token', $token->plainTextToken)
            ->with('success', 'API token created successfully. Copy it now - you won\'t be able to see it again!')
        ;
    }

    /**
     * Revoke an API token.
     *
     * @param int $id Token ID
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function revokeApiToken(int $id)
    {
        $user = auth()->user();

        // Find token belonging to current user
        if (method_exists($user, 'tokens')) {
            $token = $user->tokens()->find($id);
        } else {
            $token = PersonalAccessToken::where('id', $id)
                ->where('tokenable_type', get_class($user))
                ->where('tokenable_id', $user->id)
                ->first()
            ;
        }

        if (!$token) {
            return redirect()
                ->route('notices.settings.api-tokens')
                ->with('error', 'Token not found or you do not have permission to revoke it.')
            ;
        }

        $token->delete();

        return redirect()
            ->route('notices.settings.api-tokens')
            ->with('success', 'API token revoked successfully.')
        ;
    }
}
