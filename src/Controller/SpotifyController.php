<?php
// src/Controller/SpotifyController.php

namespace App\Controller;

use App\Entity\Artist;
use App\Entity\RefreshToken;
use Doctrine\Persistence\ManagerRegistry;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class SpotifyController extends AbstractController
{
    #[Route('/startLogin', name: 'app_spotify')]
    public function connectSpotify(ClientRegistry $clientRegistry): RedirectResponse
    {
        // Esto genera la URL de Spotify con client_id, redirect_uri y scopes
        return $clientRegistry
            ->getClient('spotify')
            ->redirect(
                [
                    'user-read-email',
                    'user-read-private',
                    'playlist-read-private',
                    'user-top-read',
                    'user-read-recently-played',
                    'app-remote-control',
                    'streaming',
                    'user-modify-playback-state',
                    'user-read-playback-state',
                    'user-library-read'
                ],
                ['prompt' => 'consent']
            );
    }

    #[Route('/connect/spotify/check', name: 'connect_spotify_check')]
    public function connectSpotifyCheck(
        ClientRegistry $clientRegistry,
        ManagerRegistry $doctrine,
        SessionInterface $session,
        LoggerInterface $logger,
        JWTTokenManagerInterface $JWTTokenManager,
        RefreshTokenManagerInterface $refreshTokenManager,
        RefreshTokenGeneratorInterface $refreshTokenGenerator
    ): RedirectResponse
    {

        $client = $clientRegistry->getClient('spotify');

        // 1) Solo UNA llamada para obtener el objeto AccessToken
        $accessTokenObject = $client->getAccessToken();
        $spotifyAccessToken = $accessTokenObject->getToken();
        $spotifyRefreshToken = $accessTokenObject->getRefreshToken();

        // 2) A partir de ese mismo token, obtener el usuario
        $spotifyUser = $client->fetchUserFromToken($accessTokenObject);


        $spotifyId   = $spotifyUser->getId();
        $email       = $spotifyUser->getEmail();        // requiere scope user-read-email
        $displayName = $spotifyUser->getDisplayName();
        $images = $spotifyUser->getImages();
        $product = $spotifyUser->getProduct();

        $profilePic = $images[0] ?? null;

        if($profilePic){
            $profilePic = $profilePic['url'];
        }
        // 2) buscar o crear tu Artist
        $em = $doctrine->getManager();
        $artistRepo = $em->getRepository(Artist::class);
        $artist = $artistRepo->findOneBy(['spotifyId' => $spotifyId]);
        if (!$artist) {
            $artist = $artistRepo->findOneBy(['username' => $email]);
            if(!$artist) {
                $artist = new Artist();
                $artist->setSpotifyId($spotifyId);
                $artist->setEmail($email);
                $artist->setName($displayName);
                $artist->setProfilePic($profilePic);
                $artist->setRoles(['ROLE_USER']);
                $artist->setRefreshToken($spotifyRefreshToken);
                $artist->setProduct($product);
            } else {
                $artist->setSpotifyId($spotifyId);
                $artist->setProfilePic($profilePic);
                $artist->setRefreshToken($spotifyRefreshToken);
                $artist->setProduct($product);
            }

            $em->persist($artist);
            $em->flush();
        }

        $artist->setRefreshToken($spotifyRefreshToken);
        $artist->setRoles(['ROLE_USER']);
        $em->persist($artist);
        $em->flush();


        $session->set('spotifyAccessToken', $spotifyAccessToken);
        $session->set('spotifyRefreshToken', $spotifyRefreshToken);


        $jwtToken = $JWTTokenManager->create($artist);
        $refreshToken = $refreshTokenGenerator->createForUserWithTtl($artist, 86400);
        $refreshTokenManager->save($refreshToken);

        return $this->redirect('http://localhost:4200/login-success?token=' . $jwtToken.'&refreshToken=' . $refreshToken);
    }

    #[Route('/spotifyToken', name: 'spotifyToken')]
    public function spotifyToken(TokenStorageInterface $tokenStorage){
        $token =  $tokenStorage->getToken()->getAttribute('spotify_access_token');
        if (!$token) {
            return $this->json(['error' => 'No token available'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json(['token' => $token]);
    }
}
