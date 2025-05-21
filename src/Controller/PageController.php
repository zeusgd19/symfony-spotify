<?php

namespace App\Controller;


use App\Services\SpotifyApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PageController extends AbstractController
{
    private String $apiKey;

    public function __construct(String $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    #[Route('/', name: 'app_page')]
    public function index(SessionInterface $session, SpotifyApiClient $spotifyService, Request $request): Response
    {

        /*
        if($this->getUser()){
            $results = $spotifyService->getTopArtists();
            $profilePic = $this->getUser()->getProfilePic();
        } else{
            $albums = $spotifyService->getNewReleases();
            $results = $spotifyService->getTopArtists();
        }

        if($request->isXmlHttpRequest()) {
            return $this->render('partials/_index_main.html.twig',[
                'imagen' => $profilePic ?? '',
                'results' => $results ?? [],
                'albums' => $albums ?? []
            ]);
        } else {
            return $this->render('page/index.html.twig',[
                'imagen' => $profilePic ?? '',
                'results' => $results ?? [],
                'albums' => $albums ?? []
            ]);
        }
        */

        return $this->render('admin/index.html.twig');
    }

    #[Route('/login', name: 'app_login')]
    public function login(): Response
    {
        return $this->render('login/index.html.twig');
    }

    #[Route('/search/{nombre}', name: 'searchWeb')]
    public function search(Request $request, SessionInterface $session, SpotifyApiClient $spotifyService, ?string $nombre = ""): Response
    {
        $results = null;

        if ($nombre !== "") {
            $results = $spotifyService->search($nombre,['artist'], 50);
        }

        $imagen = $session->get('imagen');
        if($request->isXmlHttpRequest()) {
            return $this->render('partials/_search_results.html.twig');
        }

        return $this->render('search/index.html.twig', [
            'imagen' => $imagen,
            'results' => $results->artists->items ?? []
        ]);
    }

    #[Route('/query', name: 'search')]
    public function query(Request $request,SpotifyApiClient $spotifyService): Response {
        $query = $request->get('q');
        $types = ['track','artist'];
        $limit = 10;

        $type = $request->get('type');
        $numberLimit = $request->get('limit');

        if($type){
            $types = [$type];
        }
        if($numberLimit){
            $limit = $numberLimit;
        }
        if (!$query) {
            return $this->json(['error' => 'Debes ingresar un término de búsqueda'], 400);
        }

        $results = $spotifyService->search($query, $types, $limit);
        return $this->json($results);
    }

    #[Route('/stream/{song}/{artist}', name: 'stream')]
    public function streamAudio(HttpClientInterface $httpClient, string $song, string $artist)
    {
        $slugger = new AsciiSlugger();
        $filesystem = new Filesystem();

        $safeName = $slugger->slug($song . '-' . $artist)->lower();
        $targetDir = __DIR__ . '/../../var/audio/';
        //$videoPath = $targetDir . $safeName . '.mp4';
        $audioPath = $targetDir . $safeName . '.mp3';

        // Crear directorio si no existe
        $filesystem->mkdir($targetDir);

        // Si no existe el mp3, descargar y extraer
        if (!$filesystem->exists($audioPath)) {
            // Paso 1: Buscar en YouTube (usando tu API)
            //$youtubeApi = 'https://www.googleapis.com/youtube/v3/search?part=snippet&type=video&maxResults=1&q=' . $query . '&key=' . $this->apiKey;
            //$rapiapiYoutubeApi = 'https://youtube-search-unlimited.p.rapidapi.com/ytsearch/?query=interview%20preparation';
            $apiDownload = 'https://oo6o8y6la6.execute-api.eu-central-1.amazonaws.com/default/Upload-DownloadYoutubeLandingPage';

            $response = $httpClient->request('GET', 'https://youtube-search-results.p.rapidapi.com/youtube-search/', [
                'query' => [
                    'q' => $artist . ' ' . $song
                ],
                'headers' => [
                    'X-RapidAPI-Key' => $this->apiKey,
                    'X-RapidAPI-Host' => 'youtube-search-results.p.rapidapi.com',
                ]
            ]);

            $data = $response->toArray(false);

            if (empty($data['videos'][0]['id'])) {
                throw new \Exception('No se encontró video en YouTube.');
            }

            $youtubeUrl = 'https://www.youtube.com/watch?v='. $data['videos'][0]['id'];
            // Paso 2: Obtener enlace de descarga

            $response = $httpClient->request('GET', 'https://youtube-video-fast-downloader-24-7.p.rapidapi.com/download_audio/'.$data['videos'][0]['id'],
            [
                'headers' => [
                    'x-rapidapi-key' => $this->apiKey,
                    'x-rapidapi-host' => 'youtube-video-fast-downloader-24-7.p.rapidapi.com'
                ]
            ]);
            $downloadData = $response->toArray();

            $videoUrl = $downloadData['file'];


            // Paso 3: Descargar el video
            $audioContent = $httpClient->request('GET', $videoUrl)->getContent();
            file_put_contents($audioPath, $audioContent);

            // Paso 4: Extraer audio con ffmpeg
            /*
            $process = new Process([
                'C:\Users\dariu\Downloads\ffmpeg-7.1-essentials_build\ffmpeg-7.1-essentials_build\bin\ffmpeg',
                '-i', $videoPath,
                '-vn',                   // sin video
                '-acodec', 'libmp3lame', // codec MP3
                '-q:a', '2',             // calidad (más bajo = mejor)
                '-movflags', '+faststart', // muy importante para streaming progresivo
                $audioPath
            ]);
            $process->setTimeout(60);
            $process->run();

            unlink($videoPath);


            if (!$process->isSuccessful()) {
                throw new \RuntimeException('Error al extraer audio con ffmpeg: ' . $process->getErrorOutput());
            }

            */


        }

        // Paso 5: Stream del archivo MP3
        return new BinaryFileResponse($audioPath, 200, [
            'Content-Type'        => 'audio/mpeg',
            'Content-Disposition' => 'inline; filename="' . basename($audioPath) . '"',
        ]);

    }
}
