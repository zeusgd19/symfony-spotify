<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
class ApiUserController extends AbstractController
{

    #[Route('/user/profile', name: 'app_user')]
    public function spotifyProfile(Request $request)
    {
        $authHeader = $request->headers->get('Authorization');
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'No token available'], Response::HTTP_UNAUTHORIZED);
        }
        return new JsonResponse([
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'roles' => $user->getRoles(),
            'profilePic' => $user->getProfilePic(),
            'product' => $user->getProduct(),
        ]);
    }
}