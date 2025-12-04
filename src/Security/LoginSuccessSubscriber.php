<?php

namespace App\Security;

use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class LoginSuccessSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $isFirstLogin = $user->getLastLoginAt() === null;
        $user->setLastLoginAt(new \DateTimeImmutable());
        $this->em->flush();

        $session = $event->getRequest()->getSession();
        if ($isFirstLogin) {
            $session?->getFlashBag()->add(
                'success',
                sprintf('Bienvenue %s&nbsp;!', $user->getPseudo() ?: '')
            );
        } else {
            $session?->getFlashBag()->add(
                'success',
                sprintf('Heureux de vous revoir, %s&nbsp;!', $user->getPseudo() ?: '')
            );
        }

        $url = $this->urlGenerator->generate('app_flow_home');
        $event->setResponse(new RedirectResponse($url));
    }
}
