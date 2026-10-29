<?php

namespace App\Http\Controllers;

use App\Models\User;
use Common\Auth\Actions\CreateUser;
use Common\Auth\Oauth;
use Common\Core\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OneTapAuthController extends BaseController
{
    public function config()
    {
        return $this->success([
            'client_id' => config('services.google.client_id'),
            'enabled' => (bool) settings('social.google.enable'),
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'credential' => 'required|string',
        ]);

        abort_unless((bool) settings('social.google.enable'), 404);

        // let Google validate the One Tap ID token (signature + expiry)
        $response = Http::timeout(6)->get(
            'https://oauth2.googleapis.com/tokeninfo',
            ['id_token' => $data['credential']],
        );

        if (!$response->successful()) {
            abort(422, 'Could not verify the Google sign-in.');
        }

        $claims = $response->json();

        // the token must have been issued for THIS site's google client
        if (
            ($claims['aud'] ?? null) !== config('services.google.client_id') ||
            empty($claims['email']) ||
            ($claims['email_verified'] ?? 'false') !== 'true'
        ) {
            abort(422, 'Invalid Google sign-in.');
        }

        $user = User::where('email', $claims['email'])->first();

        if (!$user) {
            $user = (new CreateUser())->execute([
                'email' => $claims['email'],
                'image' => $claims['picture'] ?? null,
                'email_verified_at' => now(),
            ]);
        }

        // link the google account so normal social login recognizes it too
        try {
            if (
                !$user
                    ->social_profiles()
                    ->where('service_name', 'google')
                    ->exists()
            ) {
                $user->social_profiles()->create([
                    'service_name' => 'google',
                    'user_service_id' => $claims['sub'] ?? null,
                    'username' => $claims['name'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('One tap profile link failed: ' . $e->getMessage());
        }

        return app(Oauth::class)->logUserIn($user, 'google');
    }
}
