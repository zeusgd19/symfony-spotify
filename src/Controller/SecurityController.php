<?php

namespace App\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SecurityController extends AbstractController
{
    private HttpClientInterface $httpClient;
    public function __construct(HttpClientInterface $client)
    {
        $this->httpClient = $client;
    }


    #[Route('/logout', name: 'app_logout', methods:["GET"])]
    public function logout(SessionInterface $session): void
    {
        $token = $session->get('token');
        $clientId = $this->getParameter('clientId');
        $clientSecret = $this->getParameter('clientSecret');

        $response = $this->httpClient->request('POST', 'https://accounts.spotify.com/api/token/revoke', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
            ],
            'body' => [
                'token' => $token,
            ]
        ]);
        // controller can be blank: it will never be called!
        throw new \Exception('Don\'t forget to activate logout in security.yaml');
    }
}
