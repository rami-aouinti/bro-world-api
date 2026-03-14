<?php

declare(strict_types=1);

namespace App\Tests\Application\Recruit\Transport\Controller\Api\V1\Offer;

use App\General\Domain\Utils\JSON;
use App\Recruit\Domain\Entity\Application as RecruitApplication;
use App\Recruit\Domain\Entity\Offer;
use App\Recruit\Domain\Entity\OfferStatusHistory;
use App\Recruit\Domain\Enum\ApplicationStatus;
use App\Recruit\Domain\Enum\ContractType;
use App\Recruit\Domain\Enum\OfferStatus;
use App\Tests\TestCase\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

class OfferWorkflowControllerTest extends WebTestCase
{
    #[TestDox('Test offer send then accept transitions offer and application statuses and audit logs.')]
    public function testSendThenAcceptTransitions(): void
    {
        $application = $this->prepareApplication();
        $client = $this->getTestClient('john-root', 'password-root');
        $baseUrl = self::API_URL_PREFIX . '/v1/recruit/applications/recruit-talent-core';

        $client->request('POST', $baseUrl . '/private/applications/' . $application->getId() . '/offers', content: JSON::encode([
            'salaryProposed' => 65000,
            'startDate' => '2026-05-01',
            'contractType' => ContractType::CDI->value,
            'comment' => 'Création offre',
        ]));
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode(), (string) $client->getResponse());

        /** @var array<string,mixed> $created */
        $created = JSON::decode((string) $client->getResponse()->getContent());

        $client->request('POST', $baseUrl . '/private/offers/' . $created['id'] . '/send', content: JSON::encode([
            'comment' => 'Envoi candidat',
        ]));
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse());

        $client->request('POST', $baseUrl . '/private/offers/' . $created['id'] . '/accept', content: JSON::encode([
            'comment' => 'Acceptée',
        ]));
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse());

        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $offer = $entityManager->getRepository(Offer::class)->find($created['id']);
        self::assertInstanceOf(Offer::class, $offer);
        self::assertSame(OfferStatus::ACCEPTED, $offer->getStatus());
        self::assertSame(ApplicationStatus::HIRED, $offer->getApplication()->getStatus());

        $history = $entityManager->getRepository(OfferStatusHistory::class)->findBy(['offer' => $offer], ['createdAt' => 'ASC']);
        self::assertCount(3, $history);
        self::assertSame('CREATED', $history[0]->getAction());
        self::assertSame('SENT', $history[1]->getAction());
        self::assertSame('ACCEPTED', $history[2]->getAction());
    }

    #[TestDox('Test offer send then decline moves application status to REJECTED.')]
    public function testSendThenDeclineTransitions(): void
    {
        $application = $this->prepareApplication();
        $client = $this->getTestClient('john-root', 'password-root');
        $baseUrl = self::API_URL_PREFIX . '/v1/recruit/applications/recruit-talent-core';

        $client->request('POST', $baseUrl . '/private/applications/' . $application->getId() . '/offers', content: JSON::encode([
            'salaryProposed' => 50000,
            'startDate' => '2026-06-15',
            'contractType' => ContractType::CDD->value,
        ]));
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        /** @var array<string,mixed> $created */
        $created = JSON::decode((string) $client->getResponse()->getContent());

        $client->request('POST', $baseUrl . '/private/offers/' . $created['id'] . '/send');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request('POST', $baseUrl . '/private/offers/' . $created['id'] . '/decline');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $offer = $entityManager->getRepository(Offer::class)->find($created['id']);
        self::assertInstanceOf(Offer::class, $offer);
        self::assertSame(OfferStatus::DECLINED, $offer->getStatus());
        self::assertSame(ApplicationStatus::REJECTED, $offer->getApplication()->getStatus());
    }

    #[TestDox('Test offer withdraw is allowed from DRAFT and logs audit action.')]
    public function testWithdrawFromDraft(): void
    {
        $application = $this->prepareApplication();
        $client = $this->getTestClient('john-root', 'password-root');
        $baseUrl = self::API_URL_PREFIX . '/v1/recruit/applications/recruit-talent-core';

        $client->request('POST', $baseUrl . '/private/applications/' . $application->getId() . '/offers', content: JSON::encode([
            'salaryProposed' => 58000,
            'startDate' => '2026-07-01',
            'contractType' => ContractType::FREELANCE->value,
        ]));
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        /** @var array<string,mixed> $created */
        $created = JSON::decode((string) $client->getResponse()->getContent());

        $client->request('POST', $baseUrl . '/private/offers/' . $created['id'] . '/withdraw', content: JSON::encode([
            'comment' => 'Retrait',
        ]));
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $offer = $entityManager->getRepository(Offer::class)->find($created['id']);
        self::assertInstanceOf(Offer::class, $offer);
        self::assertSame(OfferStatus::WITHDRAWN, $offer->getStatus());

        $history = $entityManager->getRepository(OfferStatusHistory::class)->findBy(['offer' => $offer], ['createdAt' => 'DESC']);
        self::assertNotEmpty($history);
        self::assertSame('WITHDRAWN', $history[0]->getAction());
    }

    private function prepareApplication(): RecruitApplication
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $application = $entityManager->getRepository(RecruitApplication::class)->findOneBy([
            'status' => ApplicationStatus::INTERVIEW_DONE,
        ]);

        if (!$application instanceof RecruitApplication) {
            $application = $entityManager->getRepository(RecruitApplication::class)->findOneBy([
                'status' => ApplicationStatus::WAITING,
            ]);
        }

        self::assertInstanceOf(RecruitApplication::class, $application);
        self::assertSame('john-root', $application->getJob()->getOwner()?->getUsername());
        $application->setStatus(ApplicationStatus::INTERVIEW_DONE);
        $entityManager->flush();

        return $application;
    }
}
