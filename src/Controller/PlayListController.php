<?php

namespace App\Controller;

use App\Entity\Artist;
use App\Entity\Playlist;
use App\Entity\Song;
use App\Services\SpotifyService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api', name: 'api_')]
class PlayListController extends AbstractController
{

    #[Route('/user', name: 'app_user')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getUserInfo(): JsonResponse
    {
        $user = $this->getUser();
        return $this->json([
            'user' => $user
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

    #[Route('/stream/{url}', name: 'stream')]
public function streamAudio(String $url)
{
    $spotifyUrl = 'https://p.scdn.co/mp3-preview/' . $url;

    $response = new StreamedResponse(function () use ($spotifyUrl) {
        $ch = curl_init($spotifyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_BUFFERSIZE, 4096);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) {
            echo $data;
            flush();
            return strlen($data);
        });

        // Si hay un error con cURL, mostrarlo
        if (curl_exec($ch) === false) {
            http_response_code(500);
            echo "Error al obtener el audio.";
        }
        
        curl_close($ch);
    });

    // Cabeceras necesarias
    $response->headers->set('Content-Type', 'audio/mpeg');
    $response->headers->set('Accept-Ranges', 'bytes');
    $response->headers->set('Connection', 'Keep-Alive');
    $response->headers->set('Cache-Control', 'no-cache');

    return $response;
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
