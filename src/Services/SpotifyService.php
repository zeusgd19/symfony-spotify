<?php

namespace App\Services;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SpotifyService
{
    private HttpClientInterface $httpClient;
    private string $accessToken;
    private string $refreshToken;
    private string $clientId;
    private string $clientSecret;

    public function __construct(HttpClientInterface $httpClient, AccessTokenProvider $accessTokenProvider, RefreshTokenProvider $refreshTokenProvider,ParameterBagInterface $params)
    {
        $this->httpClient = $httpClient;
        $this->accessToken = $accessTokenProvider->getAccessToken();
        $this->refreshToken = $refreshTokenProvider->getRefreshToken();
        $this->clientId = $params->get('clientId');
        $this->clientSecret = $params->get('clientSecret');

        if($this->accessToken !== "nothing") {
            $response = $this->httpClient->request('GET', 'https://api.spotify.com/v1/me', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                ],
            ]);

            if ($response->getStatusCode() === 401) {
                $newResponse = $this->httpClient->request('POST', 'https://accounts.spotify.com/api/token', [
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                    'body' => [
                        'grant_type' => 'refresh_token',
                        'refresh_token' => $this->refreshToken,
                    ]
                ]);

                $content = json_decode($newResponse->getContent(), true);

                if (isset($content['access_token'])) {
                    $accessTokenProvider->setAccessToken($content['access_token']);
                    $this->accessToken = $accessTokenProvider->getAccessToken();
                } else {
                    throw new \Exception('No se pudo obtener el nuevo access token de Spotify.');
                }
            }
        }
    }

    public function getTokenWithoutLogin() {
        $response = $this->httpClient->request('POST', 'https://accounts.spotify.com/api/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'grant_type' => 'client_credentials'
            ]
        ]);

        $tokenData = json_decode($response->getContent(), true);

        return $tokenData['access_token'];
    }



    /**
     * Obtén los artistas más escuchados del usuario.
     */
    public function getTopArtists(int $limit = 5, string $timeRange = 'short_term'): array
    {
        $response = $this->httpClient->request('GET', 'https://api.spotify.com/v1/me/top/artists', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
            ],
            'query' => [
                'limit' => $limit,
                'time_range' => $timeRange,
            ],
        ]);

        $content = json_decode($response->getContent(), true);
        return $content['items'] ?? [];
    }

    /**
     * Obtén las canciones más escuchadas del usuario.
     */
    public function getTopTracks(int $limit = 5, string $timeRange = 'short_term'): array
    {
        $response = $this->httpClient->request('GET', 'https://api.spotify.com/v1/me/top/tracks', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
            ],
            'query' => [
                'limit' => $limit,
                'time_range' => $timeRange,
            ],
        ]);

        $content = json_decode($response->getContent(), true);
        return $content['items'] ?? [];
    }

    /**
     * Obtén géneros a partir de los artistas más escuchados.
     */
    public function getGenresFromTopArtists(int $limit = 5, string $timeRange = 'short_term'): array
    {
        $topArtists = $this->getTopArtists($limit, $timeRange);

        $genres = [];
        foreach ($topArtists as $artist) {
            foreach ($artist['genres'] as $genre) {
                if (!isset($genres[$genre])) {
                    $genres[$genre] = 0;
                }
                $genres[$genre]++;
            }
        }

        // Ordena géneros por popularidad
        arsort($genres);
        return $genres;
    }

    /**
     * Genera recomendaciones basadas en artistas, canciones y géneros.
     */
    public function getRecommendations(array $seedArtists, array $seedTracks, int $limit = 5): array
    {
        $response = $this->httpClient->request('GET', 'https://api.spotify.com/v1/recommendations', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
            ],
            'query' => [
                'seed_artists' => implode(',', array_slice($seedArtists, 0, 5)), // Máximo 5
                'seed_tracks' => implode(',', array_slice($seedTracks, 0, 5)), // Máximo 5
                //'seed_genres' => implode(',', array_slice($seedGenres, 0, 5)), // Máximo 5
                'limit' => $limit,
            ],
        ]);

        $content = json_decode($response->getContent(), true);
        return $content['tracks'] ?? [];
    }

    public function search(string $query, array $types = ['track'], int $limit = 10): array {

        $newToken = $this->getTokenWithoutLogin();

        $searched = $this->httpClient->request('GET', 'https://api.spotify.com/v1/search', [
            'headers' => [
                'Authorization' => 'Bearer ' . $newToken,
            ],
            'query' => [
                'q' => $query,
                'type' => implode(',', $types),
                'limit' => $limit,
            ],
        ]);

        return json_decode($searched->getContent(), true);
    }

    public function getPopularArtists(): array
    {

        $newToken = $this->getTokenWithoutLogin();
        $response = $this->httpClient->request('GET', 'https://api.spotify.com/v1/playlists/1ZFdeTzyCpFQVhC0Y3QwIc', [
            'headers' => [
                'Authorization' => 'Bearer ' . $newToken,
            ]
        ]);


        $playlists = json_decode($response->getContent(), true);


        $artistsData = [];
        $limit = 20;
        $countdown = 0;
        $lastArtistId = 0;
        foreach ($playlists['tracks']['items'] as $item) {
            $track = $item['track'];

            if (isset($track['artists']) && is_array($track['artists'])) {
                foreach ($track['artists'] as $artist) {
                    if($countdown >= $limit) {
                        break;
                    }
                    $artistId = $artist['id'];
                    if($lastArtistId != $artistId) {
                        $artistResponse = $this->httpClient->request('GET', "https://api.spotify.com/v1/artists/$artistId", [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $newToken,
                            ]
                        ]);


                        $artistData = json_decode($artistResponse->getContent(), true);

                        $artistsData[$artistId] = $artistData;
                    }
                    $lastArtistId = $artistId;
                    $countdown++;
                }
            }
        }

        return $artistsData;
    }


    function getNewAlbums()
    {
        $newToken = $this->getTokenWithoutLogin();

        $newAlbums = $this->httpClient->request('GET', "https://api.spotify.com/v1/browse/new-releases", [
            'headers' => [
                'Authorization' => 'Bearer ' . $newToken,
            ]
        ]);

        $albums = json_decode($newAlbums->getContent(), true);

        return $albums['albums']['items'];
    }
}


