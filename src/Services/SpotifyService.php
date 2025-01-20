<?php

namespace App\Services;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SpotifyService
{
    private HttpClientInterface $httpClient;
    private string $accessToken;

    public function __construct(HttpClientInterface $httpClient, AccessTokenProvider $accessTokenProvider)
    {
        $this->httpClient = $httpClient;
        $this->accessToken = $accessTokenProvider->getAccessToken();
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
    public function getRecommendations(array $seedArtists, array $seedTracks, array $seedGenres, int $limit = 10): array
    {
        $response = $this->httpClient->request('GET', 'https://api.spotify.com/v1/recommendations', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
            ],
            'query' => [
                'seed_artists' => implode(',', array_slice($seedArtists, 0, 5)), // Máximo 5
                'seed_tracks' => implode(',', array_slice($seedTracks, 0, 5)), // Máximo 5
                'seed_genres' => implode(',', array_slice($seedGenres, 0, 5)), // Máximo 5
                'limit' => $limit,
            ],
        ]);

        $content = json_decode($response->getContent(), true);
        return $content['tracks'] ?? [];
    }
}


