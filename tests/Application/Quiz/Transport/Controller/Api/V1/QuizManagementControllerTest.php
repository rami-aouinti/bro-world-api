<?php

declare(strict_types=1);

namespace App\Tests\Application\Quiz\Transport\Controller\Api\V1;

use App\General\Domain\Utils\JSON;
use App\Platform\Domain\Entity\Application;
use App\Quiz\Domain\Entity\Quiz;
use App\Quiz\Domain\Entity\QuizAnswer;
use App\Quiz\Domain\Entity\QuizQuestion;
use App\Tests\TestCase\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

final class QuizManagementControllerTest extends WebTestCase
{
    #[TestDox('Quiz CRUD, question/answer mutations and attempts retrieval are functional.')]
    public function testQuizManagementAndAttemptsFlow(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $ownerApplication = $this->getApplicationByOwner($entityManager, 'john-root');
        $ownerClient = $this->getTestClient('john-root', 'password-root');

        $ownerClient->request('POST', self::API_URL_PREFIX . '/v1/quiz/applications/' . $ownerApplication->getSlug(), content: JSON::encode([
            'title' => 'New quiz contract',
            'description' => 'Flow test',
            'passScore' => 60,
        ]));
        self::assertSame(Response::HTTP_CREATED, $ownerClient->getResponse()->getStatusCode());
        $quizId = JSON::decode((string)$ownerClient->getResponse()->getContent(), true)['id'];

        $ownerClient->request('PUT', self::API_URL_PREFIX . '/v1/quiz/applications/' . $ownerApplication->getSlug(), content: JSON::encode([
            'title' => 'Updated quiz contract',
            'description' => 'Flow updated',
            'passScore' => 75,
        ]));
        self::assertSame(Response::HTTP_OK, $ownerClient->getResponse()->getStatusCode());

        $ownerClient->request('PATCH', self::API_URL_PREFIX . '/v1/quiz/applications/' . $ownerApplication->getSlug() . '/publish');
        self::assertSame(Response::HTTP_OK, $ownerClient->getResponse()->getStatusCode());

        $ownerClient->request('POST', self::API_URL_PREFIX . '/v1/quiz/applications/' . $ownerApplication->getSlug() . '/questions', content: JSON::encode([
            'title' => 'Newly managed question',
            'level' => 'medium',
            'category' => 'backend',
            'points' => 4,
            'answers' => [
                [
                    'label' => 'Good',
                    'correct' => true,
                ],
                [
                    'label' => 'Bad',
                    'correct' => false,
                ],
            ],
        ]));
        self::assertSame(Response::HTTP_ACCEPTED, $ownerClient->getResponse()->getStatusCode());

        $entityManager->clear();
        $quiz = $entityManager->getRepository(Quiz::class)->find($quizId);
        self::assertInstanceOf(Quiz::class, $quiz);

        $question = $entityManager->getRepository(QuizQuestion::class)->findOneBy([
            'quiz' => $quiz,
        ], [
            'createdAt' => 'DESC',
        ]);
        self::assertInstanceOf(QuizQuestion::class, $question);
        $answer = $entityManager->getRepository(QuizAnswer::class)->findOneBy([
            'question' => $question,
        ], [
            'position' => 'ASC',
        ]);
        self::assertInstanceOf(QuizAnswer::class, $answer);

        $ownerClient->request('PUT', self::API_URL_PREFIX . '/v1/quiz/questions/' . $question->getId(), content: JSON::encode([
            'title' => 'Updated question',
            'level' => 'hard',
            'category' => 'devops',
            'points' => 5,
        ]));
        self::assertSame(Response::HTTP_OK, $ownerClient->getResponse()->getStatusCode());

        $ownerClient->request('PATCH', self::API_URL_PREFIX . '/v1/quiz/questions/' . $question->getId() . '/reorder', content: JSON::encode([
            'position' => 2,
        ]));
        self::assertSame(Response::HTTP_OK, $ownerClient->getResponse()->getStatusCode());

        $ownerClient->request('PUT', self::API_URL_PREFIX . '/v1/quiz/answers/' . $answer->getId(), content: JSON::encode([
            'label' => 'Updated answer',
            'correct' => true,
            'position' => 1,
        ]));
        self::assertSame(Response::HTTP_OK, $ownerClient->getResponse()->getStatusCode());

        $ownerClient->request('PATCH', self::API_URL_PREFIX . '/v1/quiz/answers/' . $answer->getId() . '/reorder', content: JSON::encode([
            'position' => 2,
        ]));
        self::assertSame(Response::HTTP_OK, $ownerClient->getResponse()->getStatusCode());

        $userClient = $this->getTestClient('john-user', 'password-user');
        $userClient->request('POST', self::API_URL_PREFIX . '/v1/quiz/applications/' . $ownerApplication->getSlug() . '/submit', content: JSON::encode([
            'answers' => [
                [
                    'questionId' => $question->getId(),
                    'answerId' => $answer->getId(),
                ],
            ],
        ]));
        self::assertSame(Response::HTTP_OK, $userClient->getResponse()->getStatusCode());

        $userClient->request('GET', self::API_URL_PREFIX . '/v1/quiz/applications/' . $ownerApplication->getSlug() . '/attempts');
        self::assertSame(Response::HTTP_OK, $userClient->getResponse()->getStatusCode());
        $attemptsPayload = JSON::decode((string)$userClient->getResponse()->getContent(), true);
        self::assertNotEmpty($attemptsPayload['items']);

        $ownerClient->request('GET', self::API_URL_PREFIX . '/v1/quiz/applications/' . $ownerApplication->getSlug() . '/stats');
        self::assertSame(Response::HTTP_OK, $ownerClient->getResponse()->getStatusCode());
        $statsPayload = JSON::decode((string)$ownerClient->getResponse()->getContent(), true);
        self::assertArrayHasKey('attemptCount', $statsPayload);
        self::assertArrayHasKey('passRate', $statsPayload);

        $ownerClient->request('DELETE', self::API_URL_PREFIX . '/v1/quiz/answers/' . $answer->getId());
        self::assertSame(Response::HTTP_NO_CONTENT, $ownerClient->getResponse()->getStatusCode());

        $ownerClient->request('DELETE', self::API_URL_PREFIX . '/v1/quiz/questions/' . $question->getId());
        self::assertSame(Response::HTTP_NO_CONTENT, $ownerClient->getResponse()->getStatusCode());

        $ownerClient->request('PATCH', self::API_URL_PREFIX . '/v1/quiz/applications/' . $ownerApplication->getSlug() . '/unpublish');
        self::assertSame(Response::HTTP_OK, $ownerClient->getResponse()->getStatusCode());

        $ownerClient->request('DELETE', self::API_URL_PREFIX . '/v1/quiz/applications/' . $ownerApplication->getSlug());
        self::assertSame(Response::HTTP_NO_CONTENT, $ownerClient->getResponse()->getStatusCode());
    }

    #[TestDox('General quiz endpoints for put/publish/unpublish/submit are functional.')]
    public function testGeneralQuizSpecificEndpointsFlow(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $generalQuiz = $entityManager->getRepository(Quiz::class)
            ->createQueryBuilder('quiz')
            ->innerJoin('quiz.application', 'application')
            ->andWhere('application.slug = :slug')
            ->setParameter('slug', 'general')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        self::assertInstanceOf(Quiz::class, $generalQuiz);

        $adminClient = $this->getTestClient('john-admin', 'password-admin');
        $adminClient->request('PUT', self::API_URL_PREFIX . '/v1/quiz/general', content: JSON::encode([
            'title' => 'General quiz updated from alias endpoint',
            'description' => 'Alias endpoint update',
            'passScore' => 65,
        ]));
        self::assertSame(Response::HTTP_OK, $adminClient->getResponse()->getStatusCode());

        $adminClient->request('PATCH', self::API_URL_PREFIX . '/v1/quiz/general/publish');
        self::assertSame(Response::HTTP_OK, $adminClient->getResponse()->getStatusCode());

        $entityManager->clear();
        $generalQuiz = $entityManager->getRepository(Quiz::class)->findOneBy([
            'title' => 'General quiz updated from alias endpoint',
        ]);
        self::assertInstanceOf(Quiz::class, $generalQuiz);

        $question = $entityManager->getRepository(QuizQuestion::class)->findOneBy([
            'quiz' => $generalQuiz,
        ]);
        self::assertInstanceOf(QuizQuestion::class, $question);

        $answer = $entityManager->getRepository(QuizAnswer::class)->findOneBy([
            'question' => $question,
            'isCorrect' => true,
        ]);
        self::assertInstanceOf(QuizAnswer::class, $answer);

        $userClient = $this->getTestClient('john-user', 'password-user');
        $userClient->request('POST', self::API_URL_PREFIX . '/v1/quiz/general/submit', content: JSON::encode([
            'answers' => [[
                'questionId' => $question->getId(),
                'answerId' => $answer->getId(),
            ]],
        ]));
        self::assertSame(Response::HTTP_OK, $userClient->getResponse()->getStatusCode());

        $adminClient->request('PATCH', self::API_URL_PREFIX . '/v1/quiz/general/unpublish');
        self::assertSame(Response::HTTP_OK, $adminClient->getResponse()->getStatusCode());
    }

    #[TestDox('Non owner cannot mutate quiz content while admin can.')]
    public function testQuizMutationSecurity(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $quiz = $entityManager->getRepository(Quiz::class)->findOneBy([
            'isPublished' => true,
        ]);
        self::assertInstanceOf(Quiz::class, $quiz);

        $userClient = $this->getTestClient('john-user', 'password-user');
        $userClient->request('PUT', self::API_URL_PREFIX . '/v1/quiz/applications/' . $quiz->getApplication()->getSlug(), content: JSON::encode([
            'title' => 'Forbidden update',
        ]));
        self::assertSame(Response::HTTP_FORBIDDEN, $userClient->getResponse()->getStatusCode());

        $adminClient = $this->getTestClient('john-admin', 'password-admin');
        $adminClient->request('PUT', self::API_URL_PREFIX . '/v1/quiz/applications/' . $quiz->getApplication()->getSlug(), content: JSON::encode([
            'title' => 'Admin update allowed',
        ]));
        self::assertSame(Response::HTTP_OK, $adminClient->getResponse()->getStatusCode());
    }

    private function getApplicationByOwner(EntityManagerInterface $entityManager, string $username): Application
    {
        $application = $entityManager->getRepository(Application::class)
            ->createQueryBuilder('application')
            ->innerJoin('application.user', 'user')
            ->leftJoin(Quiz::class, 'quiz', 'WITH', 'quiz.application = application')
            ->andWhere('user.username = :username')
            ->andWhere('quiz.id IS NULL')
            ->setParameter('username', $username)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Application::class, $application);

        return $application;
    }
}
