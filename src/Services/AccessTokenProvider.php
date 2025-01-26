<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\Session\SessionFactory;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class AccessTokenProvider
{
    private SessionInterface $session;

    public function __construct(SessionFactory $session)
    {
        // Creamos la sesión a partir de la fábrica
        $this->session = $session->createSession();
    }

    public function getAccessToken(): ?string
    {

        return $this->session->get('token', "nothing");
    }

    public function setAccessToken(string $token): void {
        $this->session->set('token', $token);
    }


}
