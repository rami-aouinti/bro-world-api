<?php

declare(strict_types=1);

namespace App\Page\Application\Resource;

use App\General\Application\Rest\RestResource;
use App\Page\Application\DTO\About\About as AboutDto;
use App\Page\Domain\Entity\About;
use App\Page\Domain\Entity\PageLanguage;
use App\Page\Domain\Repository\Interfaces\AboutRepositoryInterface as Repository;
use App\Page\Domain\Repository\Interfaces\PageLanguageRepositoryInterface;
use RuntimeException;

class AboutResource extends RestResource
{
    public function __construct(Repository $repository, private readonly PageLanguageRepositoryInterface $pageLanguageRepository)
    {
        parent::__construct($repository);
    }

    protected function beforeCreate($dto, $entity): void { $this->applyLanguage($dto, $entity); }
    protected function beforeUpdate(string $id, $dto, $entity): void { $this->applyLanguage($dto, $entity); }
    protected function beforePatch(string $id, $dto, $entity): void { $this->applyLanguage($dto, $entity); }

    private function applyLanguage(AboutDto $dto, About $entity): void
    {
        /** @var PageLanguage|null $language */
        $language = $this->pageLanguageRepository->find($dto->getLanguageId());

        if ($language === null) {
            throw new RuntimeException('Invalid page language id provided.');
        }

        $entity->setLanguage($language);
    }
}
