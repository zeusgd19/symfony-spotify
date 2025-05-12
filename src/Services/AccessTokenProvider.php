<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\Session\SessionFactory;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Exception\TokenNotFoundException;

class AccessTokenProvider
{

    public function __construct(SessionFactory $session)
    {
        // Creamos la sesión a partir de la fábric
    }

    public function getAccessToken(string $jwt): ?string
    {

        if (!$jwt) {
            return throw new TokenNotFoundException();
        }

        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new TokenNotFoundException();// formato JWT inválido
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        return $payload['spotify_token'] ?? '';
    }

}
