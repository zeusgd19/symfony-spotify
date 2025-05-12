<?php

namespace App\Controller;

use App\Entity\Artist;
use App\Entity\Playlist;
use App\Entity\Song;
use App\Services\SessionRefresher;
use App\Services\SpotifyApiClient;
use App\Services\SpotifyService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api', name: 'api_')]
class PlayListController extends AbstractController
{

    #[Route('/user/profile', name: 'app_user')]
    public function spotifyProfile(Request $request){
        $authHeader = $request->headers->get('Authorization');
        $user = $this->getUser();
        if(!$user){
            return $this->json(['error' => 'No token available'], Response::HTTP_UNAUTHORIZED);
        }
        return new JsonResponse([
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'profilePic' => $user->getProfilePic(),
            'product' => $user->getProduct(),
        ]);
    }

    #[Route('/test-token')]
    public function testToken(TokenStorageInterface $tokenStorage): JsonResponse
    {
        $token = $tokenStorage->getToken();

        return $this->json([
            'class' => $token ? get_class($token) : null,
            'authenticated' => $token?->isAuthenticated(),
            'user' => $token?->getUser(),
        ]);
    }

    #[Route('/debug-headers')]
    public function debugHeaders(Request $request): JsonResponse
    {
        return $this->json([
            'headers' => $request->headers->all()
        ]);
    }

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


    #[Route('/playlists', name: 'app_play_list')]
    public function playlists(ManagerRegistry $doctrine)
    {
        $playlistRepository = $doctrine->getRepository(Playlist::class);
        $playlists = $playlistRepository->findAll();
        $artistRepository = $doctrine->getRepository(Artist::class);
        $artists = $artistRepository->findAll();

        $data = [];
        foreach ($playlists as $playlist) {
            $artistsName = [];
            foreach ($artists as $artist) {
                if ($artist->getPlaylist()->contains($playlist)) {
                    $artistsName[] = $artist->getName();
                }
            }
            $info = [
                'id' => $playlist->getId(),
                'albumId' => $playlist->getAlbum()->getId(),
                'title' => $playlist->getTitle(),
                'cover' => $playlist->getCover(),
                'artists' => $artistsName
            ];

            $data[] = $info;
        }

        $response = new JsonResponse($data);
	    $response->headers->set('Access-Control-Allow-Origin', '*');
        return $response;
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

    #[Route('/myPlaylists', name: 'my_play_list')]
    public function myPlaylists(SpotifyApiClient $spotifyApiClient, Request $request): JsonResponse{
        $header = $request->headers->get('Authorization');
        $jwt = substr($header, 7);
        $playlists = $spotifyApiClient->getPlaylistsWithTopTracks($jwt);

        $data = [];
        $artists = [];
        /*
        foreach ($playlists as $playlits) {
            $data['id'] = $playlits->id;
            $data['name'] = $playlits->name;
            $data['uri'] = $playlits->uri;
            $data['image'] = $playlits->images[0]->url;
            $artists[] = $data;
        }
        */
        return $this->json([
            'playlists' => $playlists
        ]);
    }

    #[Route('/songs', name: 'songs')]
    public function songs(ManagerRegistry $doctrine,Request $request){

        $data = json_decode($request->getContent(), true);

        if (!isset($data['PlayListId'])) {
            return $this->json(['error' => 'Datos incompletos'], 400);
        }

        $playListRepository = $doctrine->getRepository(Playlist::class);
        $playlist = $playListRepository->find($data['PlayListId']);

        if (!$playlist) {
            throw $this->createNotFoundException('Playlist no encontrada');
        }

        $album = $playlist->getAlbum();
        $songRepository = $doctrine->getRepository(Song::class);
        $songs = $songRepository->findBy(['album' => $album]);

        $data = [];
        foreach ($songs as $song){
            $artistsName = [];
            foreach ($song->getArtists() as $artist){
                $artistsName[] = $artist->getName();
            }
            $info = [
                'id' => $song->getId(),
                'albumId' => $song->getAlbum()->getId(),
                'title' => $song->getTitle(),
                'image' => $song->getImage(),
                'artists' =>$artistsName,
                'album' => $song->getAlbum()->getName()
            ];

            $data[] = $info;
        }

        $response = new JsonResponse($data);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        return $response;
    }


    #[Route('/search/{search}', name: 'search')]
    public function search(SpotifyApiClient $spotifyApiClient, Request $request, string $search): JsonResponse
    {
        $header = $request->headers->get('Authorization');
        $jwt = substr($header, 7);

        $searched = $spotifyApiClient->search($search);

        $songsStd = $searched->tracks->items;
        $artistsStd = $searched->artists->items;

        $songsSearched = json_decode(json_encode($songsStd), true);
        $artistsSearched = json_decode(json_encode($artistsStd), true);

        $artists = [];
        $dataArtists = [];
        foreach ($artistsSearched as $artist) {
            $image = $artist['images'][0] ?? $artist['images'][1] ?? $artist['images'][2] ?? '';
            $url = $image['url'] ?? null;
            $dataArtists['id'] = $artist['id'];
            $dataArtists['name'] = $artist['name'];
            $dataArtists['uri'] = $artist['uri'];
            $dataArtists['image'] = $url;
            $artists[] = $dataArtists;
        }

        $songs = [];
        $dataSongs = [];
        foreach ($songsSearched as $track) {
            $dataSongs['id'] = $track['id'];
            $dataSongs['name'] = $track['name'];
            $dataSongs['uri'] = $track['uri'];
            $dataSongs['album'] = $track['album'];
            $dataSongs['duration_ms'] = $track['duration_ms'];
            $dataSongs['artistsId'] = $track['artists'][0]['id'];
            $songs[] = $dataSongs;
        }
        return $this->json([
            'songs' => $songs,
            'artists' => $artists
        ]);
    }


    #[Route('/recommendations', name: 'recommendations')]
    public function recommendations(SpotifyService $spotifyService,SessionInterface $session){
        $topArtists = $spotifyService->getTopArtists();
        $topTracks = $spotifyService->getTopTracks();
        $seedArtists = [];
        $seedTracks = [];
        $seedGenres = [];
        foreach ($topArtists as $topArtist){
            $seedArtists[] = $topArtist['id'];
            if(!in_array($topArtist['genre'][0] ?? null,$seedGenres)){
                $seedGenres[] = $topArtist['genres'][0] ?? null;
            }
        }
        //BQBpgqzd8wxq3jrVsVUVpFb3B931uC30wEETqUD6nT9wj7Hgn_PKGkwC5yZHkxSJ_cKSBzqpUyb_Ld6jhiPHsx8gVuoBBymtRQsYT54jBdGIrYhaFueMADNXMQ1-6Akf1FD61iodYci-Dl0zz0pXs1vz8mpu2xAqB8yeoK7__r6EwWF85EYh_oCzhLyL-LeD6Ua8_VcdrD8miwft2Df0tFtB_cPK8U_tL4vNCPo5sA

        foreach ($topTracks as $topTrack){
            $seedTracks[] = $topTrack['id'];
        }

        //curl --request GET --url 'https://api.spotify.com/v1/recommendations?seed_artists=4NHQUGzhtTLFvgF5SZesLK&seed_genres=classical%2Ccountry&seed_tracks=0c6xIDDpzE81m2q797ordA' --header 'Authorization: Bearer         BQBpgqzd8wxq3jrVsVUVpFb3B931uC30wEETqUD6nT9wj7Hgn_PKGkwC5yZHkxSJ_cKSBzqpUyb_Ld6jhiPHsx8gVuoBBymtRQsYT54jBdGIrYhaFueMADNXMQ1-6Akf1FD61iodYci-Dl0zz0pXs1vz8mpu2xAqB8yeoK7__r6EwWF85EYh_oCzhLyL-LeD6Ua8_VcdrD8miwft2Df0tFtB_cPK8U_tL4vNCPo5sA'
        //$recommendations = $spotifyService->getRecommendations($seedArtists, $seedTracks);

        dd($topArtists);
        exit();

    }
}
