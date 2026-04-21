<?php

declare(strict_types=1);

namespace App\Page\Application\Service;

use App\General\Domain\Service\Interfaces\MailerServiceInterface;
use App\Page\Domain\Entity\PublicContactRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;
use Twig\Environment as Twig;

final readonly class PublicContactMutationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerServiceInterface $mailerService,
        private Twig $twig,
        #[Autowire('%env(resolve:APP_SENDER_EMAIL)%')]
        private string $appSenderEmail,
        #[Autowire('%env(resolve:SITE_CONTACT_EMAIL)%')]
        private string $siteContactEmail,
    ) {
    }

    /**
     * @param array<string, string> $payload
     * @throws Throwable
     */
    public function create(array $payload): PublicContactRequest
    {
        $contactRequest = (new PublicContactRequest())
            ->setFirstName($payload['firstName'])
            ->setLastName($payload['lastName'])
            ->setEmail($payload['email'])
            ->setType($payload['type'])
            ->setMessage($payload['message']);

        $this->entityManager->persist($contactRequest);
        $this->entityManager->flush();

        $body = $this->twig->render('Emails/public_contact_request.html.twig', [
            'contactRequest' => $contactRequest,
        ]);

        $this->mailerService->sendMail(
            'Nouveau message du formulaire de contact',
            $this->appSenderEmail,
            $this->siteContactEmail,
            $body,
        );

        return $contactRequest;
    }
}
