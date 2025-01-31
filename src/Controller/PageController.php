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
    public function index(SessionInterface $session, SpotifyService $spotifyService): Response
    {
        if($this->getUser()){
	$results = $spotifyService->getTopArtists();
        $imagen = $session->get('imagen');
        return $this->render('page/index.html.twig',[
            'imagen' => $imagen,
            'results' => $results ?? []
        ]);
        } else{
        $albums = $spotifyService->getNewAlbums();
        $results = $spotifyService->getPopularArtists();
        $imagen = $session->get('imagen');
        return $this->render('page/index.html.twig',[
            'imagen' => $imagen,
            'results' => $results ?? [],
            'albums' => $albums ?? []
        ]);
    }
    #[Route('/login', name: 'app_page')]
    public function login(): Response
    {
        return $this->render('login/index.html.twig');
    }

    #[Route('/search/{nombre}', name: 'searchWeb')]
    public function search(Request $request, SessionInterface $session, SpotifyService $spotifyService, ?string $nombre = ""): Response
    {
        $results = null;

        if ($nombre !== "") {
            $results = $spotifyService->search($nombre, ['artist'], 50);
        }

        $imagen = $session->get('imagen');
        if($request->isXmlHttpRequest()) {
            return $this->render('partials/_search_results.html.twig');
        }

        return $this->render('search/index.html.twig', [
            'imagen' => $imagen,
            'results' => $results['artists']['items'] ?? []
        ]);
    }

    #[Route('/query', name: 'search')]
    public function query(Request $request,SpotifyService $spotifyService): Response {
        $query = $request->get('q');

        if (!$query) {
            return $this->json(['error' => 'Debes ingresar un término de búsqueda'], 400);
        }

        $results = $spotifyService->search($query, ['track', 'artist'], 50);
        return $this->json($results);
    }
}
