<?php

declare(strict_types=1);

namespace App\Abonnement\Transport\Command;

use App\Abonnement\Domain\Entity\News;
use App\General\Domain\Service\Interfaces\MailerServiceInterface;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: self::NAME,
    description: 'Execute pending News and send email notifications to subscribed users.',
)]
final class ExecutePendingNewsCommand extends Command
{
    final public const string NAME = 'app:abonnement:execute-news';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerServiceInterface $mailerService,
        #[Autowire('%env(resolve:APP_SENDER_EMAIL)%')]
        private readonly string $senderEmail,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $pendingNews = $this->entityManager->getRepository(News::class)
            ->createQueryBuilder('news')
            ->where('news.executed = :executed')
            ->andWhere('news.executeAt <= :now')
            ->setParameter('executed', false)
            ->setParameter('now', new DateTimeImmutable())
            ->orderBy('news.executeAt', 'ASC')
            ->getQuery()
            ->getResult();

        if ($pendingNews === []) {
            $io->success('No pending news to execute.');

            return Command::SUCCESS;
        }

        $subscribedUsers = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('user')
            ->where('user.abonnement = :abonnement')
            ->setParameter('abonnement', true)
            ->getQuery()
            ->getResult();

        foreach ($pendingNews as $news) {
            if (!$news instanceof News) {
                continue;
            }

            foreach ($subscribedUsers as $user) {
                if (!$user instanceof User) {
                    continue;
                }

                $this->mailerService->sendMail(
                    $news->getTitle(),
                    $this->senderEmail,
                    $user->getEmail(),
                    $news->getDescription(),
                );
            }

            $news->setExecuted(true);
            $this->entityManager->persist($news);
        }

        $this->entityManager->flush();

        $io->success('Pending news executed and emails sent.');

        return Command::SUCCESS;
    }
}
