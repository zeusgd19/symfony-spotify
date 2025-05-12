<?php
namespace App\Services;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SessionRefresher
{
    private $session;
    private $tokenStorage;

    public function __construct(SessionInterface $session, TokenStorageInterface $tokenStorage)
    {
        $this->session = $session;
        $this->tokenStorage = $tokenStorage;
    }

    public function refreshSession(UserInterface $user, string $firewall = 'main'): void
    {
        $token = new UsernamePasswordToken(
            $user,
            $firewall,
            $user->getRoles()
        );

        // Mantén los atributos existentes si los hay
        $oldToken = unserialize($this->session->get('_security_'.$firewall));
        if ($oldToken) {
            foreach ($oldToken->getAttributes() as $key => $value) {
                $token->setAttribute($key, $value);
            }
        }

        $this->session->set('_security_'.$firewall, serialize($token));
        $this->session->save();
    }

    public function isExpired(): bool
    {
        if (null === $this->tokenStorage->getToken()) {
            return true;
        }

        return false;
    }
}