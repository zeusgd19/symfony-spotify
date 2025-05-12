<?php

namespace App\Services;

use App\Entity\Artist;
use Symfony\Component\HttpFoundation\Session\SessionFactory;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;

class RefreshTokenProvider
{

    public function __construct(SessionFactory $session)
    {
    }

    public function getRefreshToken(string $jwt): ?string
    {
        if (!$jwt) {
            return null;
        }

        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null; // formato JWT inválido
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        return $payload['refresh_token'] ?? null;
    }
}
