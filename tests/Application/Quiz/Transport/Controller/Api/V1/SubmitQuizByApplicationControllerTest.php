<?php

declare(strict_types=1);

namespace App\Tests\Application\Quiz\Transport\Controller\Api\V1;

use App\General\Domain\Utils\JSON;
use App\Quiz\Domain\Entity\Quiz;
use App\Quiz\Domain\Entity\QuizAnswer;
use App\Quiz\Domain\Entity\QuizQuestion;
use App\Tests\TestCase\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

final class SubmitQuizByApplicationControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/quiz/applications';

    #[TestDox('A user can submit a quiz and receive a computed score.')]
    public function testSubmitQuizReturnsScore(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $quiz = $this->getAnyPublishedQuiz($entityManager);
        $questions = $entityManager->getRepository(QuizQuestion::class)->findBy([
            'quiz' => $quiz,
        ], [
            'position' => 'ASC',
        ]);

        $answersPayload = [];
        foreach ($questions as $question) {
            $correctAnswerId = null;
            foreach ($question->getAnswers() as $answer) {
                if ($answer instanceof QuizAnswer && $answer->isCorrect()) {
                    $correctAnswerId = $answer->getId();
                    break;
                }
            }

            self::assertNotNull($correctAnswerId);
            $answersPayload[] = [
                'questionId' => $question->getId(),
                'answerId' => $correctAnswerId,
            ];
        }

        $client = $this->getTestClient('john-user', 'password-user');
        $client->request(
            'POST',
            $this->baseUrl . '/' . $quiz->getApplication()->getSlug() . '/submit',
            content: JSON::encode([
                'answers' => $answersPayload,
            ])
        );

        $response = $client->getResponse();
        $content = $response->getContent();

        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);
        self::assertSame(100.0, (float)$responseData['score']);
        self::assertTrue((bool)$responseData['passed']);
        self::assertSame(count($questions), (int)$responseData['totalQuestions']);
        self::assertSame(count($questions), (int)$responseData['correctAnswers']);
    }

    #[TestDox('A quiz submission is scored with single-choice rules per question.')]
    public function testSubmitQuizAppliesSingleChoiceScoring(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $quiz = $this->getAnyPublishedQuiz($entityManager);
        $questions = $entityManager->getRepository(QuizQuestion::class)->findBy([
            'quiz' => $quiz,
        ], [
            'position' => 'ASC',
        ]);

        $answersPayload = [];
        $expectedTotalPoints = 0;
        $expectedEarnedPoints = 0;
        $expectedCorrectAnswers = 0;
        $hasForcedWrongAnswer = false;

        foreach ($questions as $question) {
            $expectedTotalPoints += $question->getPoints();

            $correctAnswerId = null;
            $wrongAnswerId = null;
            foreach ($question->getAnswers() as $answer) {
                if (!$answer instanceof QuizAnswer) {
                    continue;
                }

                if ($answer->isCorrect() && $correctAnswerId === null) {
                    $correctAnswerId = $answer->getId();
                }

                if (!$answer->isCorrect() && $wrongAnswerId === null) {
                    $wrongAnswerId = $answer->getId();
                }
            }

            self::assertNotNull($correctAnswerId);

            $selectedAnswerId = $correctAnswerId;
            if (!$hasForcedWrongAnswer && $wrongAnswerId !== null) {
                $selectedAnswerId = $wrongAnswerId;
                $hasForcedWrongAnswer = true;
            } else {
                $expectedEarnedPoints += $question->getPoints();
                $expectedCorrectAnswers++;
            }

            $answersPayload[] = [
                'questionId' => $question->getId(),
                'answerId' => $selectedAnswerId,
            ];
        }

        self::assertTrue($hasForcedWrongAnswer, 'The selected quiz fixture must include at least one wrong answer option.');

        $expectedScore = $expectedTotalPoints > 0
            ? round(($expectedEarnedPoints / $expectedTotalPoints) * 100, 2)
            : 0.0;

        $client = $this->getTestClient('john-user', 'password-user');
        $client->request(
            'POST',
            $this->baseUrl . '/' . $quiz->getApplication()->getSlug() . '/submit',
            content: JSON::encode([
                'answers' => $answersPayload,
            ])
        );

        $response = $client->getResponse();
        $content = $response->getContent();

        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);
        self::assertSame($expectedScore, (float)$responseData['score']);
        self::assertSame($expectedCorrectAnswers, (int)$responseData['correctAnswers']);
        self::assertSame($expectedEarnedPoints, (int)$responseData['earnedPoints']);
        self::assertSame($expectedTotalPoints, (int)$responseData['totalPoints']);
    }

    #[TestDox('A quiz submission score is based on submitted questions only.')]
    public function testSubmitQuizScoresOnlySubmittedQuestions(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $quiz = $this->getAnyPublishedQuiz($entityManager);
        $questions = $entityManager->getRepository(QuizQuestion::class)->findBy([
            'quiz' => $quiz,
        ], [
            'position' => 'ASC',
        ]);

        self::assertNotEmpty($questions);

        $subset = array_slice($questions, 0, 10);
        $answersPayload = [];
        $expectedTotalPoints = 0;
        foreach ($subset as $question) {
            $expectedTotalPoints += $question->getPoints();
            $correctAnswerId = null;
            foreach ($question->getAnswers() as $answer) {
                if ($answer instanceof QuizAnswer && $answer->isCorrect()) {
                    $correctAnswerId = $answer->getId();
                    break;
                }
            }

            self::assertNotNull($correctAnswerId);
            $answersPayload[] = [
                'questionId' => $question->getId(),
                'answerId' => $correctAnswerId,
            ];
        }

        $client = $this->getTestClient('john-user', 'password-user');
        $client->request(
            'POST',
            $this->baseUrl . '/' . $quiz->getApplication()->getSlug() . '/submit',
            content: JSON::encode([
                'answers' => $answersPayload,
            ])
        );

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);
        self::assertSame(100.0, (float)$responseData['score']);
        self::assertSame(count($subset), (int)$responseData['totalQuestions']);
        self::assertSame(count($subset), (int)$responseData['answeredQuestions']);
        self::assertSame(count($subset), (int)$responseData['correctAnswers']);
        self::assertSame($expectedTotalPoints, (int)$responseData['totalPoints']);
        self::assertSame($expectedTotalPoints, (int)$responseData['earnedPoints']);
    }

    #[TestDox('GET quiz by application does not expose answers correction data.')]
    public function testGetQuizByApplicationDoesNotExposeAnswerCorrection(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $quiz = $this->getAnyPublishedQuiz($entityManager);

        $client = $this->getTestClient('john-user', 'password-user');
        $client->request('GET', $this->baseUrl . '/' . $quiz->getApplication()->getSlug());

        $response = $client->getResponse();
        $content = $response->getContent();

        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);
        self::assertIsArray($responseData['questions'] ?? null);

        foreach ($responseData['questions'] as $question) {
            self::assertIsArray($question['answers'] ?? null);

            foreach ($question['answers'] as $answer) {
                self::assertIsArray($answer);
                self::assertArrayNotHasKey('correct', $answer);
            }
        }
    }

    #[TestDox('GET quiz by application returns 404 for an unpublished quiz.')]
    public function testGetQuizByApplicationReturnsNotFoundWhenQuizIsNotPublished(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $quiz = $this->getAnyPublishedQuiz($entityManager);
        $quiz->setPublished(false);
        $entityManager->flush();

        $client = $this->getTestClient('john-user', 'password-user');
        $client->request('GET', $this->baseUrl . '/' . $quiz->getApplication()->getSlug());

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    #[TestDox('Submitting a quiz returns 404 when the quiz is not published.')]
    public function testSubmitQuizReturnsNotFoundWhenQuizIsNotPublished(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $quiz = $this->getAnyPublishedQuiz($entityManager);
        $quiz->setPublished(false);
        $entityManager->flush();

        $client = $this->getTestClient('john-user', 'password-user');
        $client->request(
            'POST',
            $this->baseUrl . '/' . $quiz->getApplication()->getSlug() . '/submit',
            content: JSON::encode([
                'answers' => [],
            ])
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    #[TestDox('Submitting invalid quiz payload returns 422.')]
    public function testSubmitQuizWithInvalidPayloadReturnsValidationError(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $quiz = $this->getAnyPublishedQuiz($entityManager);

        $client = $this->getTestClient('john-user', 'password-user');
        $client->request(
            'POST',
            $this->baseUrl . '/' . $quiz->getApplication()->getSlug() . '/submit',
            content: JSON::encode([
                'answers' => [[
                    'questionId' => 12,
                ]],
            ])
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[TestDox('Submitting quiz payload with non-string answer id returns 422.')]
    public function testSubmitQuizWithNonStringAnswerIdReturnsValidationError(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $quiz = $this->getAnyPublishedQuiz($entityManager);

        $client = $this->getTestClient('john-user', 'password-user');
        $client->request(
            'POST',
            $this->baseUrl . '/' . $quiz->getApplication()->getSlug() . '/submit',
            content: JSON::encode([
                'answers' => [[
                    'questionId' => 'q_1',
                    'answerId' => ['a_1'],
                ]],
            ])
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    private function getAnyPublishedQuiz(EntityManagerInterface $entityManager): Quiz
    {
        $quiz = $entityManager->getRepository(Quiz::class)->createQueryBuilder('quiz')
            ->leftJoin('quiz.application', 'application')->addSelect('application')
            ->andWhere('quiz.isPublished = true')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Quiz::class, $quiz);

        return $quiz;
    }
}
