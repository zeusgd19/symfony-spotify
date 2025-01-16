<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SpotifyController extends AbstractController
{

    private string $redirectUri = 'http://localhost:8000/callback';
    private string $scopes = 'user-read-email user-read-private playlist-read-private';
    private HttpClientInterface $httpClient;
    public function  __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }


    #[Route('/startLogin', name: 'app_spotify')]
    public function index(): Response
    {
        $clientId = $this->getParameter('clientId');
        $authUrl = 'https://accounts.spotify.com/authorize?' . http_build_query([
                'response_type' => 'code',
                'client_id' => $clientId,
                'redirect_uri' => $this->redirectUri,
                'scope' => $this->scopes,
            ]);

        return new RedirectResponse($authUrl);
    }

    #[Route('/callback', name: 'callback')]
    public function callback(Request $request,SessionInterface $session): Response
    {
        $clientId = $this->getParameter('clientId');
        $clientSecret = $this->getParameter('clientSecret');
        $code = $request->query->get('code');

        if (!$code) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }

        $response = $this->httpClient->request('POST', 'https://accounts.spotify.com/api/token', [
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUri,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ],
        ]);

        $data = json_decode($response->getContent(), true);

        if (isset($data['access_token'])) {
            $accessToken = $data['access_token'];
            $refreshToken = $data['refresh_token'];
            $expiresIn = $data['expires_in'] ?? 3600;

            $userResponse = $this->httpClient->request('GET', 'https://api.spotify.com/v1/me', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);

            $userData = json_decode($userResponse->getContent(), true);

            $email = $userData['email'] ?? null;
            $spotifyId = $userData['id'] ?? null;
            $profileImages = $userData['images'] ?? [];

            if (!$email || !$spotifyId) {
                return new Response('Email o Spotify ID no pueden ser nulos.', Response::HTTP_FORBIDDEN);
            }

            $profileImageUrl = !empty($profileImages) ? $profileImages[0]['url'] : null;

            $session->set('imagen', $profileImageUrl);
            $session->set('correoElectronico', $email);


            return $this->redirectToRoute('app_page');
        } else {
            return new Response('Ha habido un problema con el inicio de sesion', Response::HTTP_FORBIDDEN);
        }
    }
}
