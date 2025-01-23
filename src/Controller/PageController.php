<?php

namespace App\Controller;


use App\Services\SpotifyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class PageController extends AbstractController
{
    #[Route('/', name: 'app_page')]
    public function index(SessionInterface $session): Response
    {
        $imagen = $session->get('imagen');
        return $this->render('page/index.html.twig',[
            'imagen' => $imagen
        ]);
    }

    #[Route('/login', name: 'app_login')]
    public function login(): Response
    {
        return $this->render('login/index.html.twig');
    }

    #[Route('/search', name: 'search')]
    public function searchForSongs(Request $request,SpotifyService $spotifyService): Response {
        $query = $request->get('q');

        if (!$query) {
            return $this->json(['error' => 'Debes ingresar un término de búsqueda'], 400);
        }

        $results = $spotifyService->search($query, ['track', 'artist'], 10);
        return $this->json($results);
    }
}
