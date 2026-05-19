<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        try {
            /** @var AbstractProvider $googleProvider */
            $googleProvider = Socialite::driver('google');
            $googleUser = $googleProvider->stateless()->user();

            if (empty($googleUser->email)) {
                return redirect($this->loginUrl($frontendUrl, 'No email returned from Google account.'));
            }

            $user = User::query()
                ->where('google_id', $googleUser->id)
                ->orWhere('email', $googleUser->email)
                ->first();

            if (! $user) {
                $user = User::create([
                    'name' => $googleUser->name ?? $googleUser->email,
                    'email' => $googleUser->email,
                    'google_id' => $googleUser->id,
                    'provider' => 'google',
                    'avatar' => $googleUser->avatar,
                    'password' => bcrypt(Str::random(24)),
                    'role_id' => Role::query()->where('slug', 'viewer')->value('id'),
                ]);
            } elseif (! $user->google_id) {
                $user->update([
                    'google_id' => $googleUser->id,
                    'provider' => 'google',
                    'avatar' => $googleUser->avatar ?? $user->avatar,
                ]);
            }

            $token = $user->createToken('google-token')->plainTextToken;

            return redirect($frontendUrl.'/dashboard?api_token='.urlencode($token));
        } catch (\Throwable $e) {
            Log::error('Google OAuth Error', [
                'message' => $e->getMessage(),
            ]);

            return redirect($this->loginUrl($frontendUrl, 'Authentication failed. Please try again.'));
        }
    }

    private function loginUrl(string $frontendUrl, string $message): string
    {
        return $frontendUrl.'/login?error='.urlencode($message);
    }
}
