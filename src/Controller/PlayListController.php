<?php

namespace App\Controller;

use App\Entity\Artist;
use App\Entity\Playlist;
use App\Entity\Song;
use App\Services\SpotifyService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
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

    #[Route('/stream-audio/{trackId}', name: 'stream_audio')]
    public function streamAudio(Request $request, string $trackId): Response
    {

        // Ruta del archivo temporal
        $tempAudioFile = $this->getParameter('kernel.project_dir') . "/var/tmp/track_{$trackId}.mp3";

        // Ejecuta el comando SpotDL para descargar la canción (solo si no está descargada aún)
        $command = [
            'spotdl',
            "https://open.spotify.com/track/{$trackId}",
            "--output", $tempAudioFile,
        ];

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            return new Response("Error al descargar la canción", Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Configura la respuesta para el streaming de la canción
        $response = new StreamedResponse(function () use ($tempAudioFile) {
            $handle = fopen($tempAudioFile, 'rb');
            while (!feof($handle)) {
                echo fread($handle, 1024 * 8); // 8KB por fragmento
                flush(); // Asegúrate de enviar los datos al cliente
            }
            fclose($handle);

            // Elimina el archivo después de transmitir
            unlink($tempAudioFile);
        });

        // Configura los encabezados para audio MP3
        $response->headers->set('Content-Type', 'audio/mpeg');
        $response->headers->set('Content-Disposition', 'inline; filename="track.mp3"');

        return $response;
    }

/*

    $process = new Process(['python3', $scriptSearchPath, $nombre]);
    $process->setTimeout(300);
    $process->run();

    if (!$process->isSuccessful()) {
        // Captura la salida de error también
        return new JsonResponse(['error' => 'Error executing script', 'details' => $process->getErrorOutput()], 500);
    }

    $output = json_decode($process->getOutput(), true);

    $process = new Process(['python3', $scriptAudioPath, $output['audio_url']]);
    $process->setTimeout(300);
    $process->run();

    $output = json_decode($process->getOutput(), true);
    $command = "ffmpeg -i {$output['audio_url']} -vn -acodec libmp3lame -f mp3 pipe:1";

    // Ejecuta el comando FFmpeg
    $audioStream = shell_exec($command);

    // Verifica si se obtuvo correctamente el stream de audio
    if ($audioStream === null) {
        return new Response("Error al procesar el video", 500);
    }

    // Envía el audio al navegador en el formato correcto para la reproducción
    $response = new Response($audioStream);
    $response->headers->set('Content-Type', 'audio/mpeg');
    $response->headers->set('Content-Disposition', 'inline; filename="audio.mp3"');

    return $response;

}
*/


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
