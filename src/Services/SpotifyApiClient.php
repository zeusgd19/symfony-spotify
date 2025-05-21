<?php
// src/Service/SpotifyApiClient.php
namespace App\Services;

use KnpU\OAuth2ClientBundle\Client\OAuth2Client;
use Psr\Log\LoggerInterface;
use SpotifyWebAPI\SpotifyWebAPI;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\TokenNotFoundException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SpotifyApiClient
{
    private SpotifyWebAPI        $api;

    private OAuth2Client $spotifyClient;
    private AccessTokenProvider $accessTokenProvider;
    private RefreshTokenProvider $refreshTokenProvider;
    private TokenStorageInterface $tokenStorage;

    public function __construct(
        OAuth2Client $spotifyClient,
        HttpClientInterface $httpClient,
        AccessTokenProvider $accessTokenProvider,
        RefreshTokenProvider $refreshTokenProvider,
        TokenStorageInterface $tokenStorage,
    ) {
        $this->spotifyClient         = $spotifyClient;
        $this->accessTokenProvider   = $accessTokenProvider;
        $this->refreshTokenProvider  = $refreshTokenProvider;
        $this->tokenStorage         = $tokenStorage;
        $this->api = new SpotifyWebAPI();
    }

    private function getValidAccessToken(string $jwt = ''): SpotifyWebAPI
    {
        $currentToken = $this->accessTokenProvider->getAccessToken($jwt);

        return $this->api->setAccessToken($currentToken);
    }

    /**
     * Cliente sin login: client_credentials grant.
     */
    public function getTokenWithoutLogin(): SpotifyWebAPI
    {
        $clientCredentialsToken = $this->spotifyClient
            ->getOAuth2Provider()
            ->getAccessToken('client_credentials');

        return $this->api->setAccessToken($clientCredentialsToken->getToken());
    }

    /** @return array */
    public function getTopArtists(string $jwt, int $limit = 8, string $timeRange = 'short_term'): array
    {
        try {
            $this->getValidAccessToken($jwt);
            // El método del wrapper para top artists
            $response = $this->api->getMyTop('artists', [
                'limit'      => $limit,
                'time_range' => $timeRange,
            ]);

            return json_decode(json_encode($response->items), true); // objetos ArtistSimplified
        } catch (TokenNotFoundException $tnfe){
            $this->getTokenWithoutLogin();
            $response = $this->api->getPlaylist('1ZFdeTzyCpFQVhC0Y3QwIc');

            $artistIds = [];
            foreach ($response->tracks->items as $item) {
                $track = $item->track;

                if (isset($track->artists) && is_array($track->artists)) {
                    foreach ($track->artists as $artist) {
                        $artistIds[] = $artist->id;
                    }
                }
            }

            // Elimina IDs repetidos
            $artistIds = array_unique($artistIds);

            // Limita a los primeros 20
            $artistIds = array_slice($artistIds, 0, 20);

 //           dump($this->api->getArtists($artistIds));
  //          exit();

            $response = $this->api->getArtists($artistIds);
            $array =  json_decode(json_encode($response), true);
            return $array['artists'];
        }

    }

    public function getMyTracks(string $jwt, int $limit = 8, string $timeRange = 'short_term')
    {
        $this->getValidAccessToken($jwt);
        $response = $this->api->getMySavedTracks();

        return $response->items;
    }

    /** @return array */
    public function getTopTracks(string $jwt, int $limit = 5, string $timeRange = 'short_term'): array
    {
        $this->getValidAccessToken($jwt);
        $response = $this->api->getMyTop('tracks', [
            'limit'      => $limit,
            'time_range' => $timeRange,
        ]);

        return $response->items; // objetos Track
    }

    public function getMyPlaylists(String $jwt, int $limit = 15): array
    {
        $this->getValidAccessToken($jwt);
        $response = $this->api->getMyPlaylists([
            'limit'      => $limit
        ]);

        return $response->items;
    }

    public function getPlaylistTracks(string $jwt, string $playlistId): array
    {
        $this->getValidAccessToken($jwt);
        $response = $this->api->getPlaylistTracks($playlistId, [
            'limit' => 100
        ]);

        return $response->items;
    }

    public function getTracksByArtist(string $jwt, string $artistId)
    {
        $this->getValidAccessToken($jwt);
        $response = $this->api->getArtistTopTracks($artistId,[]);

        return $response;
    }

    public function getArtist(string $jwt, string $artistId)
    {
        $this->getValidAccessToken($jwt);
        $response = $this->api->getArtist($artistId);

        return $response;
    }

    public function getPlaylistsWithTopTracks(string $jwt, int $playlistLimit = 50, int $topTrackLimit = 50): array
    {
        $topTracks = $this->getTopTracks($jwt,$topTrackLimit);
        $playlists = $this->getMyPlaylists($jwt,$playlistLimit);
        // Crear un set de IDs de canciones top para comparación rápida
        $topTrackIds = array_map(fn($track) => $track->id, $topTracks);
        $topTrackIdsSet = array_flip($topTrackIds); // para acceso rápido con isset()

        $matchingPlaylists = [];

        foreach ($playlists as $playlist) {
            $playlistId = $playlist->id;
            $playlistTracks = $this->getPlaylistTracks($jwt,$playlistId);

            foreach ($playlistTracks as $item) {
                if (!isset($item->track->id)) {
                    continue;
                }

                $trackId = $item->track->id;

                if (isset($topTrackIdsSet[$trackId])) {
                    // Esta playlist contiene al menos una canción top
                    $matchingPlaylists[] = $playlist;
                    break; // no hace falta seguir revisando esta playlist
                }
            }
        }

        return $matchingPlaylists;
    }

    /** @return array|object */
    public function getRecommendations(array $seedArtists, array $seedTracks, int $limit = 5)
    {
        $this->getValidAccessToken();
        return $this->api->getRecommendations([
            'seed_artists' => $seedArtists,
            'seed_tracks'  => $seedTracks,
            'limit'        => $limit,
        ]);
    }


    /** @return array|object */
    public function search(string $query, array $types = ['track','artist'], int $limit = 10)
    {
        // Para búsquedas PADRÓN usa client credentials
        $this->getTokenWithoutLogin();

        return $this->api->search($query, $types, [
            'limit' => $limit,
        ]);
    }

    /** @return array */
    public function getNewReleases(int $limit = 20)
    {
        $this->getTokenWithoutLogin();

        $response = $this->api->getNewReleases([
            'limit' => $limit,
        ]);

        return $response->albums->items; // objetos SimpleAlbum
    }
}
