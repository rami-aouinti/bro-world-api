<?php

declare(strict_types=1);

namespace App\Tests\Application\Quiz\Transport\Controller\Api\V1;

use App\General\Domain\Utils\JSON;
use App\Quiz\Application\Message\CreateQuizQuestionCommand;
use App\Quiz\Application\MessageHandler\CreateQuizQuestionCommandHandler;
use App\Quiz\Domain\Entity\Quiz;
use App\Tests\TestCase\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class QuizCacheInvalidationTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/quiz/applications';

    #[TestDox('Creating a question invalidates quiz read and quiz stats caches for the application.')]
    public function testCreateQuestionInvalidatesQuizAndStatsCache(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $quiz = $this->getPublishedQuizOwnedByRoot($entityManager);

        $readerClient = $this->getTestClient('john-user', 'password-user');
        $readerClient->request('GET', $this->baseUrl . '/' . $quiz->getApplication()->getSlug());
        $initialQuizPayload = JSON::decode((string)$readerClient->getResponse()->getContent(), true);
        self::assertSame(Response::HTTP_OK, $readerClient->getResponse()->getStatusCode());

        $readerClient->request('GET', $this->baseUrl . '/' . $quiz->getApplication()->getSlug() . '/stats');
        $initialStatsPayload = JSON::decode((string)$readerClient->getResponse()->getContent(), true);
        self::assertSame(Response::HTTP_OK, $readerClient->getResponse()->getStatusCode());

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async_priority_high');
        $transport->reset();

        $ownerClient = $this->getTestClient('john-root', 'password-root');
        $ownerClient->request('POST', $this->baseUrl . '/' . $quiz->getApplication()->getSlug() . '/questions', content: JSON::encode([
            'title' => 'Cache invalidation question',
            'level' => 'easy',
            'category' => 'general',
            'points' => 2,
            'answers' => [
                [
                    'label' => 'Right answer',
                    'correct' => true,
                ],
                [
                    'label' => 'Wrong answer',
                    'correct' => false,
                ],
            ],
        ]));

        self::assertSame(Response::HTTP_ACCEPTED, $ownerClient->getResponse()->getStatusCode());

        $envelopes = $transport->getSent();
        self::assertCount(1, $envelopes);

        $command = $envelopes[0]->getMessage();
        self::assertInstanceOf(CreateQuizQuestionCommand::class, $command);

        /** @var CreateQuizQuestionCommandHandler $handler */
        $handler = static::getContainer()->get(CreateQuizQuestionCommandHandler::class);
        $handler($command);

        $readerClient->request('GET', $this->baseUrl . '/' . $quiz->getApplication()->getSlug());
        $updatedQuizPayload = JSON::decode((string)$readerClient->getResponse()->getContent(), true);
        self::assertSame(Response::HTTP_OK, $readerClient->getResponse()->getStatusCode());

        $readerClient->request('GET', $this->baseUrl . '/' . $quiz->getApplication()->getSlug() . '/stats');
        $updatedStatsPayload = JSON::decode((string)$readerClient->getResponse()->getContent(), true);
        self::assertSame(Response::HTTP_OK, $readerClient->getResponse()->getStatusCode());

        self::assertCount(count($initialQuizPayload['questions']) + 1, $updatedQuizPayload['questions']);
        self::assertSame('Cache invalidation question', $updatedQuizPayload['questions'][count($updatedQuizPayload['questions']) - 1]['title']);

        self::assertSame((int)$initialStatsPayload['questionCount'] + 1, (int)$updatedStatsPayload['questionCount']);
        self::assertSame((int)$initialStatsPayload['answerCount'] + 2, (int)$updatedStatsPayload['answerCount']);
    }

    #[TestDox('Creating a question requires at least one correct answer.')]
    public function testCreateQuestionValidationRequiresAtLeastOneCorrectAnswer(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $quiz = $this->getPublishedQuizOwnedByRoot($entityManager);

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async_priority_high');
        $transport->reset();

        $ownerClient = $this->getTestClient('john-root', 'password-root');
        $ownerClient->request('POST', $this->baseUrl . '/' . $quiz->getApplication()->getSlug() . '/questions', content: JSON::encode([
            'title' => 'Invalid question no correct answer',
            'level' => 'easy',
            'category' => 'general',
            'points' => 2,
            'answers' => [
                [
                    'label' => 'Wrong A',
                    'correct' => false,
                ],
                [
                    'label' => 'Wrong B',
                    'correct' => false,
                ],
            ],
        ]));

        self::assertSame(Response::HTTP_ACCEPTED, $ownerClient->getResponse()->getStatusCode());

        $envelopes = $transport->getSent();
        self::assertCount(1, $envelopes);
        $command = $envelopes[0]->getMessage();
        self::assertInstanceOf(CreateQuizQuestionCommand::class, $command);

        /** @var CreateQuizQuestionCommandHandler $handler */
        $handler = static::getContainer()->get(CreateQuizQuestionCommandHandler::class);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->expectExceptionMessage('At least one answer must be marked as correct.');
        $handler($command);
    }

    #[TestDox('Creating a question rejects multi-choice answers for single-choice quizzes.')]
    public function testCreateQuestionValidationRejectsMultipleCorrectAnswers(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $quiz = $this->getPublishedQuizOwnedByRoot($entityManager);

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async_priority_high');
        $transport->reset();

        $ownerClient = $this->getTestClient('john-root', 'password-root');
        $ownerClient->request('POST', $this->baseUrl . '/' . $quiz->getApplication()->getSlug() . '/questions', content: JSON::encode([
            'title' => 'Invalid question multi-correct',
            'level' => 'easy',
            'category' => 'general',
            'points' => 2,
            'answers' => [
                [
                    'label' => 'Right A',
                    'correct' => true,
                ],
                [
                    'label' => 'Right B',
                    'correct' => true,
                ],
            ],
        ]));

        self::assertSame(Response::HTTP_ACCEPTED, $ownerClient->getResponse()->getStatusCode());

        $envelopes = $transport->getSent();
        self::assertCount(1, $envelopes);
        $command = $envelopes[0]->getMessage();
        self::assertInstanceOf(CreateQuizQuestionCommand::class, $command);

        /** @var CreateQuizQuestionCommandHandler $handler */
        $handler = static::getContainer()->get(CreateQuizQuestionCommandHandler::class);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->expectExceptionMessage('Single-choice quizzes require exactly one correct answer per question.');
        $handler($command);
    }

    #[TestDox('Creating a question rejects empty answer labels.')]
    public function testCreateQuestionValidationRejectsEmptyLabels(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $quiz = $this->getPublishedQuizOwnedByRoot($entityManager);

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async_priority_high');
        $transport->reset();

        $ownerClient = $this->getTestClient('john-root', 'password-root');
        $ownerClient->request('POST', $this->baseUrl . '/' . $quiz->getApplication()->getSlug() . '/questions', content: JSON::encode([
            'title' => 'Invalid question empty label',
            'level' => 'easy',
            'category' => 'general',
            'points' => 2,
            'answers' => [
                [
                    'label' => '    ',
                    'correct' => true,
                ],
                [
                    'label' => 'Wrong',
                    'correct' => false,
                ],
            ],
        ]));

        self::assertSame(Response::HTTP_ACCEPTED, $ownerClient->getResponse()->getStatusCode());

        $envelopes = $transport->getSent();
        self::assertCount(1, $envelopes);
        $command = $envelopes[0]->getMessage();
        self::assertInstanceOf(CreateQuizQuestionCommand::class, $command);

        /** @var CreateQuizQuestionCommandHandler $handler */
        $handler = static::getContainer()->get(CreateQuizQuestionCommandHandler::class);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->expectExceptionMessage('Answer labels must be non-empty.');
        $handler($command);
    }

    private function getPublishedQuizOwnedByRoot(EntityManagerInterface $entityManager): Quiz
    {
        $quiz = $entityManager->getRepository(Quiz::class)
            ->createQueryBuilder('quiz')
            ->innerJoin('quiz.application', 'application')->addSelect('application')
            ->innerJoin('quiz.owner', 'owner')
            ->andWhere('owner.username = :username')
            ->andWhere('quiz.isPublished = true')
            ->setParameter('username', 'john-root')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Quiz::class, $quiz);

        return $quiz;
    }
}
