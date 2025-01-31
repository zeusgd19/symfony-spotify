<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\Session\SessionFactory;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class RefreshTokenProvider
{
    private SessionInterface $session;

    public function __construct(SessionFactory $session)
    {
        // Creamos la sesión a partir de la fábrica
        $this->session = $session->createSession();
    }

    public function getRefreshToken(): ?string
    {
        return $this->session->get('refresh_token', "nothing");
    }
}
