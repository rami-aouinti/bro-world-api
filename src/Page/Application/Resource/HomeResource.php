<?php

declare(strict_types=1);

namespace App\Page\Application\Resource;

use App\General\Application\Rest\RestResource;
use App\General\Application\DTO\Interfaces\RestDtoInterface;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\Page\Application\DTO\Home\Home as HomeDto;
use App\Page\Domain\Entity\Home;
use App\Page\Domain\Repository\Interfaces\PageLanguageRepositoryInterface;
use App\Page\Domain\Repository\Interfaces\HomeRepositoryInterface as Repository;
use App\Page\Domain\Entity\PageLanguage;
use RuntimeException;

class HomeResource extends RestResource
{
    public function __construct(Repository $repository, private readonly PageLanguageRepositoryInterface $pageLanguageRepository)
    {
        parent::__construct($repository);
    }

    public function beforeCreate(RestDtoInterface $dto, EntityInterface $entity): void { $this->applyLanguage($dto, $entity); }
    public function beforeUpdate(string &$id, RestDtoInterface $dto, EntityInterface $entity): void { $this->applyLanguage($dto, $entity); }
    public function beforePatch(string &$id, RestDtoInterface $dto, EntityInterface $entity): void { $this->applyLanguage($dto, $entity); }

    private function applyLanguage(RestDtoInterface $dto, EntityInterface $entity): void
    {
        if (!$dto instanceof HomeDto || !$entity instanceof Home) {
            return;
        }

        /** @var PageLanguage|null $language */
        $language = $this->pageLanguageRepository->find($dto->getLanguageId());

        if ($language === null) {
            throw new RuntimeException('Invalid page language id provided.');
        }

        $entity->setLanguage($language);
    }
}
