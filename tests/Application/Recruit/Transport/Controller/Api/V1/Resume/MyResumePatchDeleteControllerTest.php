<?php

declare(strict_types=1);

namespace App\Tests\Application\Recruit\Transport\Controller\Api\V1\Resume;

use App\General\Domain\Utils\JSON;
use App\Recruit\Domain\Entity\Resume;
use App\Tests\TestCase\WebTestCase;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MyResumePatchDeleteControllerTest extends WebTestCase
{
    /**
     * @throws Throwable
     */
    #[TestDox('Test that PATCH /v1/recruit/private/me/resumes/{resumeId} updates connected user resume.')]
    public function testThatPatchMyResumeUpdatesResume(): void
    {
        $resumeId = $this->getResumeIdForUsername('john-root');

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('PATCH', self::API_URL_PREFIX . '/v1/recruit/private/me/resumes/' . $resumeId, content: JSON::encode([
            'documentUrl' => 'https://localhost/uploads/resumes/updated.pdf',
            'experiences' => [
                [
                    'title' => 'Staff Engineer',
                    'description' => 'Architecture API',
                ],
            ],
        ]));

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);
        self::assertSame('https://localhost/uploads/resumes/updated.pdf', $payload['documentUrl']);

        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $resume = $entityManager->getRepository(Resume::class)->find($resumeId);
        self::assertInstanceOf(Resume::class, $resume);
        self::assertSame('https://localhost/uploads/resumes/updated.pdf', $resume->getDocumentUrl());
        self::assertCount(1, $resume->getExperiences());
        self::assertSame('Staff Engineer', $resume->getExperiences()->first()->getTitle());
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that PATCH /v1/recruit/private/me/resumes/{resumeId} forbids foreign user.')]
    public function testThatPatchMyResumeRejectsForeignUser(): void
    {
        $resumeId = $this->getResumeIdForUsername('john-root');

        $client = $this->getTestClient('john-user', 'password-user');
        $client->request('PATCH', self::API_URL_PREFIX . '/v1/recruit/private/me/resumes/' . $resumeId, content: JSON::encode([
            'documentUrl' => 'https://localhost/uploads/resumes/should-fail.pdf',
        ]));

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that DELETE /v1/recruit/private/me/resumes/{resumeId} deletes connected user resume.')]
    public function testThatDeleteMyResumeDeletesResume(): void
    {
        $resumeId = $this->getResumeIdForUsername('john-api');

        $client = $this->getTestClient('john-api', 'password-api');
        $client->request('DELETE', self::API_URL_PREFIX . '/v1/recruit/private/me/resumes/' . $resumeId);

        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $deletedResume = $entityManager->getRepository(Resume::class)->find($resumeId);
        self::assertNull($deletedResume);
    }

    /**
     * @return non-empty-string
     */
    private function getResumeIdForUsername(string $username): string
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = $entityManager->getRepository(User::class)->findOneBy([
            'username' => $username,
        ]);
        self::assertInstanceOf(User::class, $user);

        $resume = $entityManager->getRepository(Resume::class)->findOneBy([
            'owner' => $user,
        ]);
        self::assertInstanceOf(Resume::class, $resume);

        return $resume->getId();
    }
}
