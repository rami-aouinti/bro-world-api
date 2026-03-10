<?php

declare(strict_types=1);

namespace App\Tool\Transport\Command\Elastic;

use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\General\Transport\Command\Traits\SymfonyStyleTrait;
use App\Shop\Application\Projection\ShopProductProjection;
use App\Shop\Domain\Entity\Product;
use App\Shop\Infrastructure\Repository\ProductRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_map;

#[AsCommand(
    name: self::NAME,
    description: 'Index shop products in Elasticsearch.',
)]
final class ReindexShopProductsCommand extends Command
{
    use SymfonyStyleTrait;

    final public const string NAME = 'elastic:reindex:shops';

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ElasticsearchServiceInterface $elasticsearchService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $indexed = 0;

        /** @var Product $product */
        foreach ($this->productRepository->findBy([], [
            'createdAt' => 'DESC',
        ]) as $product) {
            $this->elasticsearchService->index(ShopProductProjection::INDEX_NAME, $product->getId(), [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'price' => $product->getPrice(),
                'categoryId' => $product->getCategory()?->getId(),
                'categoryName' => $product->getCategory()?->getName() ?? '',
                'tags' => array_map(static fn ($tag): string => $tag->getLabel(), $product->getTags()->toArray()),
                'updatedAt' => $product->getUpdatedAt()?->format(DATE_ATOM),
            ]);
            $indexed++;
        }

        if ($input->isInteractive()) {
            $this->getSymfonyStyle($input, $output)->success('Shop products indexed: ' . $indexed);
        }

        return Command::SUCCESS;
    }
}
