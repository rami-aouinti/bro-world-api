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
