<?php

namespace App\Http\Controllers;

use App\Contracts\OAuthServiceContract;
use App\Exceptions\UnknownProviderException;
use App\Models\User;
use App\Services\OAuth\GithubService;
use App\Services\OAuth\TwitchService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function authenticate(Request $request, string $provider): Response
    {
        if (!$code = $request->query('code')) {
            return redirect('/');
        }

        try {
            $service = $this->getProvider($provider);
            $response = $service->auth($code);
            $providerUser = $service->getUser($response['access_token']);

            $user = $this->findOrCreate($provider, $providerUser);
            Auth::login($user);

            return redirect('/dashboard');
        } catch (UnknownProviderException $e) {
            return redirect('/dashboard')->withErrors(['error' => 'The given provider was invalid'], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (GuzzleException $e) {
            return redirect('/dashboard')->withErrors(['error' => 'Failed to communicate with ' . ucfirst($provider)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

    }

    private function findOrCreate(string $provider, array $providerUser)
    {
        $payload = [
            $provider . '_username' => $providerUser['login'] ?? $providerUser['data'][0]['login'],
            "name" => $providerUser['name'] ?? $providerUser['data'][0]['display_name'],
            $provider . "_id" => $providerUser['id'] ?? $providerUser['data'][0]['id'],
            "email" => $providerUser['email'] ?? $providerUser['data'][0]['email'],
            'image' => $providerUser['avatar_url'] ?? $providerUser['data'][0]['profile_image_url']
        ];


        if ($user = User::where($provider . "_id", $payload[$provider . '_id'])->first()) {
            return $user;
        }

        if ($user = User::where('email', $payload['email'])->first()) {
            $user->update([
                $provider . "_id" => $payload[$provider . '_id'],
                $provider . "_username" => $payload[$provider . '_username']
            ]);

            return $user;
        }
        $imagePath = 'avatars/' . Uuid::uuid4()->toString() . '.png';
        Storage::put('public/' . $imagePath, file_get_contents($payload['image']));
        $payload['image_path'] = $imagePath;

        return User::create($payload);
    }

    public function getLogout() {
        Auth::logout();

        return redirect('/');
    }

    /**
     * @throws UnknownProviderException
     */
    private function getProvider(string $provider): OAuthServiceContract
    {
        return match ($provider) {
            'github' => new GithubService(),
            'twitch' => new TwitchService(),
            default => throw new UnknownProviderException(),
        };
    }
}
