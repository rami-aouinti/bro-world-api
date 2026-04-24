<?php

declare(strict_types=1);

namespace App\Notification\Application\Service;

use App\Notification\Domain\Entity\NotificationTemplate;
use App\Notification\Infrastructure\Repository\NotificationTemplateRepository;
use Throwable;

use function is_array;
use function is_int;
use function is_string;

final readonly class NotificationTemplateSyncService
{
    public function __construct(
        private MailjetTemplateService $mailjetTemplateService,
        private NotificationTemplateRepository $notificationTemplateRepository,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function sync(int $batchSize = 100): int
    {
        $offset = 0;
        $synced = 0;

        do {
            $items = $this->mailjetTemplateService->listTemplates($batchSize, $offset);
            foreach ($items as $item) {
                $providerTemplateId = $item['id'] ?? null;
                if (!is_int($providerTemplateId)) {
                    continue;
                }

                $template = $this->notificationTemplateRepository->findOneByProviderTemplateId($providerTemplateId)
                    ?? (new NotificationTemplate())->setProviderTemplateId($providerTemplateId);

                $template
                    ->setName(is_string($item['name'] ?? null) ? $item['name'] : '')
                    ->setIsActive((bool)($item['isActive'] ?? false))
                    ->setVariables(is_array($item['variables'] ?? null) ? $item['variables'] : []);

                $this->notificationTemplateRepository->save($template, false);
                ++$synced;
            }

            $this->notificationTemplateRepository->getEntityManager()->flush();
            $offset += $batchSize;
        } while ($items !== []);

        return $synced;
    }
}
