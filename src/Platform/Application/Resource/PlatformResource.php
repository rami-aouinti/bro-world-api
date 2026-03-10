<?php

declare(strict_types=1);

namespace App\Platform\Application\Resource;

use App\General\Application\DTO\Interfaces\RestDtoInterface;
use App\General\Application\Rest\RestResource;
use App\General\Application\Service\CacheInvalidationService;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\Platform\Domain\Entity\Platform as Entity;
use App\Platform\Domain\Repository\Interfaces\PlatformRepositoryInterface as Repository;

/**
 * @package App\Platform
 *
 * @psalm-suppress LessSpecificImplementedReturnType
 * @codingStandardsIgnoreStart
 *
 * @method Entity getReference(string $id, ?string $entityManagerName = null)
 * @method \App\Platform\Infrastructure\Repository\PlatformRepository getRepository()
 * @method Entity[] find(?array $criteria = null, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?array $search = null, ?string $entityManagerName = null)
 * @method Entity|null findOne(string $id, ?bool $throwExceptionIfNotFound = null, ?string $entityManagerName = null)
 * @method Entity|null findOneBy(array $criteria, ?array $orderBy = null, ?bool $throwExceptionIfNotFound = null, ?string $entityManagerName = null)
 * @method Entity create(RestDtoInterface $dto, ?bool $flush = null, ?bool $skipValidation = null, ?string $entityManagerName = null)
 * @method Entity update(string $id, RestDtoInterface $dto, ?bool $flush = null, ?bool $skipValidation = null, ?string $entityManagerName = null)
 * @method Entity patch(string $id, RestDtoInterface $dto, ?bool $flush = null, ?bool $skipValidation = null, ?string $entityManagerName = null)
 * @method Entity delete(string $id, ?bool $flush = null, ?string $entityManagerName = null)
 * @method Entity save(EntityInterface $entity, ?bool $flush = null, ?bool $skipValidation = null, ?string $entityManagerName = null)
 *
 * @codingStandardsIgnoreEnd
 */
class PlatformResource extends RestResource
{
    /**
     * @param \App\Platform\Infrastructure\Repository\PlatformRepository $repository
     */
    public function __construct(
        Repository $repository,
        private readonly CacheInvalidationService $cacheInvalidationService,
    ) {
        parent::__construct($repository);
    }

    public function afterCreate(RestDtoInterface $restDto, EntityInterface $entity): void
    {
        $this->cacheInvalidationService->invalidatePublicPlatformListCaches();
    }

    public function afterUpdate(string &$id, RestDtoInterface $restDto, EntityInterface $entity): void
    {
        $this->cacheInvalidationService->invalidatePublicPlatformListCaches();
    }

    public function afterPatch(string &$id, RestDtoInterface $dto, EntityInterface $entity): void
    {
        $this->cacheInvalidationService->invalidatePublicPlatformListCaches();
    }

    public function afterDelete(string &$id, EntityInterface $entity): void
    {
        $this->cacheInvalidationService->invalidatePublicPlatformListCaches();
    }

    /**
     * @return array<int, Entity>
     */
    public function findPublicEnabled(): array
    {
        return $this->getRepository()->findPublicEnabled();
    }
}
