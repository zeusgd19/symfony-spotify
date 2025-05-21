<?php

namespace App\Controller;
use App\Services\SpotifyApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
class ApiArtistsController extends AbstractController
{
    #[Route('/popularArtists', name: 'popular_artists')]
    public function popularArtists(SpotifyApiClient $spotifyApiClient, Request $request): JsonResponse
    {
//        $expired = $sessionRefresher->isExpired();
//        if($expired){
//            $sessionRefresher->refreshSession($this->getUser());
//        }
        $header = $request->headers->get('Authorization');
        $jwt = substr($header, 7);
        $popularArtists = $spotifyApiClient->getTopArtists($jwt);

        $data = [];
        $artists = [];
        foreach ($popularArtists as $popularArtist) {
            $data['id'] = $popularArtist['id'];
            $data['name'] = $popularArtist['name'];
            $data['uri'] = $popularArtist['uri'];
            $data['image'] = $popularArtist['images'][0]['url'];
            $artists[] = $data;
        }

        return $this->json([
            'artists' => $artists
        ]);
    }

    #[Route('/artist/{artistId}', name: 'app_songs_by_artist')]
    public function songsByArtists(SpotifyApiClient $spotifyApiClient, Request $request): JsonResponse
    {
        $header = $request->headers->get('Authorization');
        $jwt = substr($header, 7);
        $artistId = $request->get('artistId');
        $songs = $spotifyApiClient->getTracksByArtist($jwt, $artistId);
        $artist = $spotifyApiClient->getArtist($jwt, $artistId);
        $tracks = $songs->tracks;

        $data = [];
        $tracksArray = [];

        $artistData['id'] = $artist->id;
        $artistData['name'] = $artist->name;
        $artistData['uri'] = $artist->uri;
        $artistData['image'] = $artist->images[0]->url;
        foreach ($tracks as $track) {
            $data['id'] = $track->id;
            $data['name'] = $track->name;
            $data['uri'] = $track->uri;
            $data['album'] = $track->album;
            $data['duration_ms'] = $track->duration_ms;
            $tracksArray[] = $data;
        }
//        return new JsonResponse([
//            'id' => $tracks['id'],
//            'name' => $tracks['name'],
//            'duration_ms' => $tracks['duration_ms'],
//            'uri' => $tracks['uri'],
//            'album' => $tracks['album'],
//        ]);

        return $this->json([
            'songs' => $tracksArray,
            'artist' => $artistData
        ]);
    }
}