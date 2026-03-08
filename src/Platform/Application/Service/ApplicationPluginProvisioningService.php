<?php

declare(strict_types=1);

namespace App\Platform\Application\Service;

use App\Calendar\Domain\Entity\Calendar;
use App\Calendar\Infrastructure\Repository\CalendarRepository;
use App\Chat\Domain\Entity\Chat;
use App\Chat\Infrastructure\Repository\ChatRepository;
use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Enum\BlogType;
use App\Blog\Infrastructure\Repository\BlogRepository;
use App\Platform\Domain\Entity\Application;
use App\Quiz\Domain\Entity\Quiz;
use App\Quiz\Infrastructure\Repository\QuizRepository;
use App\Platform\Domain\Enum\PluginKey;
use Doctrine\ORM\EntityManagerInterface;

use function in_array;

final class ApplicationPluginProvisioningService
{
    public function __construct(
        private readonly CalendarRepository $calendarRepository,
        private readonly ChatRepository $chatRepository,
        private readonly BlogRepository $blogRepository,
        private readonly QuizRepository $quizRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<int, PluginKey> $pluginKeys
     */
    public function provision(Application $application, array $pluginKeys): void
    {
        if (in_array(PluginKey::CALENDAR, $pluginKeys, true)) {
            $this->provisionCalendar($application);
        }

        if (in_array(PluginKey::CHAT, $pluginKeys, true)) {
            $this->provisionChat($application);
        }
        if (in_array(PluginKey::BLOG, $pluginKeys, true)) {
            $this->provisionBlog($application);
        }

        if (in_array(PluginKey::QUIZ, $pluginKeys, true)) {
            $this->provisionQuiz($application);
        }
    }

    private function provisionCalendar(Application $application): void
    {
        if ($this->calendarRepository->findOneByApplication($application) instanceof Calendar) {
            return;
        }

        $calendar = (new Calendar())
            ->setTitle('Default calendar')
            ->setUser($application->getUser())
            ->setApplication($application);

        $this->entityManager->persist($calendar);
    }

    private function provisionChat(Application $application): void
    {
        if ($this->chatRepository->findOneByApplication($application) instanceof Chat) {
            return;
        }

        $application->ensureGeneratedSlug();

        $chat = (new Chat())
            ->setApplication($application)
            ->setApplicationSlug($application->getSlug());

        $this->entityManager->persist($chat);
    }

    private function provisionBlog(Application $application): void
    {
        if ($this->blogRepository->findOneByApplication($application) instanceof Blog) {
            return;
        }

        $blog = (new Blog())
            ->setTitle($application->getTitle() . ' Blog')
            ->setOwner($application->getUser())
            ->setType(BlogType::APPLICATION)
            ->setApplication($application);

        $this->entityManager->persist($blog);
    }

    private function provisionQuiz(Application $application): void
    {
        if ($this->quizRepository->findOneByApplication($application) instanceof Quiz) {
            return;
        }

        $quiz = (new Quiz())
            ->setApplication($application)
            ->setOwner($application->getUser());

        $this->entityManager->persist($quiz);
    }

}
