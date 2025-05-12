<?php
namespace App\EventListener;

use App\Entity\Artist;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Psr\Log\LoggerInterface;

class JWTCreatedListener
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $em,
        private LoggerInterface $logger
    ) {}

    public function onJWTCreated(JWTCreatedEvent $event): void
    {

        $user = $event->getUser();

        if (!$user instanceof Artist) {
            $this->logger->warning('[JWTCreatedListener] Usuario no es instancia de Artist');
            return;
        }

        $refreshToken = $user->getRefreshToken();
        if (!$refreshToken) {
            $this->logger->warning('[JWTCreatedListener] No hay refresh token disponible');
            return;
        }

        $client = $this->clientRegistry->getClient('spotify');

        try {
            $newToken = $client->getOAuth2Provider()->getAccessToken('refresh_token', [
                'refresh_token' => $refreshToken,
            ]);

            $spotifyAccessToken = $newToken->getToken();
            $this->logger->info('[JWTCreatedListener] Spotify token obtenido correctamente');

            // Guardar nuevo refresh token si ha cambiado
            if ($newToken->getRefreshToken() && $newToken->getRefreshToken() !== $refreshToken) {
                $user->setRefreshToken($newToken->getRefreshToken());
                $this->em->persist($user);
                $this->em->flush();

                $this->logger->info('[JWTCreatedListener] Refresh token actualizado');
            }

            // Añadir token al payload del JWT
            $payload = $event->getData();
            $payload['email'] = $user->getEmail();
            $payload['spotify_token'] = $spotifyAccessToken;
            $event->setData($payload);

        } catch (\Throwable $e) {
            $this->logger->error('[JWTCreatedListener] Error al refrescar el token de Spotify', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
