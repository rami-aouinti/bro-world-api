<?php

declare(strict_types=1);

namespace App\Page\Application\Service;

use App\General\Application\Service\CacheKeyConventionService;
use App\Page\Domain\Entity\About;
use App\Page\Domain\Entity\Contact;
use App\Page\Domain\Entity\Faq;
use App\Page\Domain\Entity\Home;
use App\Page\Domain\Entity\PageLanguage;
use App\Page\Infrastructure\Repository\AboutRepository;
use App\Page\Infrastructure\Repository\ContactRepository;
use App\Page\Infrastructure\Repository\FaqRepository;
use App\Page\Infrastructure\Repository\HomeRepository;
use App\Page\Infrastructure\Repository\PageLanguageRepository;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final readonly class PublicPageReadService
{
    private const int TTL = 600;

    public function __construct(
        private PageLanguageRepository $pageLanguageRepository,
        private HomeRepository $homeRepository,
        private AboutRepository $aboutRepository,
        private ContactRepository $contactRepository,
        private FaqRepository $faqRepository,
        private CacheInterface $cache,
        private CacheKeyConventionService $cacheKeyConventionService,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     * @throws InvalidArgumentException
     */
    public function getHome(string $languageCode): ?array
    {
        return $this->getPageContent('home', $languageCode);
    }

    /**
     * @return array<string, mixed>|null
     * @throws InvalidArgumentException
     */
    public function getAbout(string $languageCode): ?array
    {
        return $this->getPageContent('about', $languageCode);
    }

    /**
     * @return array<string, mixed>|null
     * @throws InvalidArgumentException
     */
    public function getContact(string $languageCode): ?array
    {
        return $this->getPageContent('contact', $languageCode);
    }

    /**
     * @return array<string, mixed>|null
     * @throws InvalidArgumentException
     */
    public function getFaq(string $languageCode): ?array
    {
        return $this->getPageContent('faq', $languageCode);
    }

    public function resolveLanguage(string $languageCode): ?PageLanguage
    {
        /** @var PageLanguage|null $language */
        $language = $this->pageLanguageRepository->findOneBy([
            'code' => $languageCode,
        ]);

        return $language;
    }

    /**
     * @return array<string, mixed>|null
     * @throws InvalidArgumentException
     */
    private function getPageContent(string $page, string $languageCode): ?array
    {
        $cacheKey = $this->cacheKeyConventionService->buildPublicPageKey($page, $languageCode);

        /** @var array<string, mixed>|null $content */
        $content = $this->cache->get($cacheKey, function (ItemInterface $item) use ($page, $languageCode): ?array {
            $item->expiresAfter(self::TTL);

            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->tagPublicPage());
            }

            $language = $this->resolveLanguage($languageCode);
            if ($language === null) {
                return null;
            }

            return match ($page) {
                'home' => $this->readHome($language),
                'about' => $this->readAbout($language),
                'contact' => $this->readContact($language),
                'faq' => $this->readFaq($language),
                default => null,
            };
        });

        return $content;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readHome(PageLanguage $language): ?array
    {
        /** @var Home|null $entity */
        $entity = $this->homeRepository->findOneBy([
            'language' => $language,
        ]);

        return $entity?->getContent();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readAbout(PageLanguage $language): ?array
    {
        /** @var About|null $entity */
        $entity = $this->aboutRepository->findOneBy([
            'language' => $language,
        ]);

        return $entity?->getContent();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readContact(PageLanguage $language): ?array
    {
        /** @var Contact|null $entity */
        $entity = $this->contactRepository->findOneBy([
            'language' => $language,
        ]);

        return $entity?->getContent();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readFaq(PageLanguage $language): ?array
    {
        /** @var Faq|null $entity */
        $entity = $this->faqRepository->findOneBy([
            'language' => $language,
        ]);

        return $entity?->getContent();
    }
}
