<?php

declare(strict_types=1);

namespace App\Tests\Unit\General\Application\MessageHandler;

use App\Crm\Infrastructure\Repository\TaskRepository;
use App\General\Application\Message\EntityCreated;
use App\General\Application\Message\EntityDeleted;
use App\General\Application\Message\EntityPatched;
use App\General\Application\MessageHandler\EntityProjectionHandler;
use App\General\Application\Service\CacheInvalidationService;
use App\General\Application\Service\CriticalViewWarmer;
use App\General\Application\Service\MessageIdempotenceGuard;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\Platform\Domain\Entity\Application;
use App\Platform\Infrastructure\Repository\ApplicationRepository;
use App\Recruit\Infrastructure\Repository\JobRepository;
use App\School\Domain\Entity\Exam;
use App\School\Domain\Entity\School;
use App\School\Domain\Entity\SchoolClass;
use App\School\Domain\Entity\Teacher;
use App\School\Infrastructure\Repository\ExamRepository;
use App\Shop\Infrastructure\Repository\ProductRepository;
use PHPUnit\Framework\TestCase;

final class EntityProjectionHandlerSchoolCacheTest extends TestCase
{
    public function testSchoolExamCreateInvalidatesExamAndClassCachesWithResolvedApplicationSlug(): void
    {
        $exam = $this->buildExamWithApplicationSlug('campus-alpha');

        $cacheInvalidationService = $this->createMock(CacheInvalidationService::class);
        $cacheInvalidationService->expects(self::once())
            ->method('invalidateSchoolExamListCaches')
            ->with('campus-alpha');
        $cacheInvalidationService->expects(self::once())
            ->method('invalidateSchoolClassListCaches')
            ->with('campus-alpha');

        $examRepository = $this->createMock(ExamRepository::class);
        $examRepository->expects(self::once())->method('find')->with('exam-1')->willReturn($exam);

        $elastic = $this->createMock(ElasticsearchServiceInterface::class);
        $elastic->expects(self::once())->method('index');

        $handler = $this->buildHandler(
            examRepository: $examRepository,
            cacheInvalidationService: $cacheInvalidationService,
            elasticsearchService: $elastic,
        );

        $handler(new EntityCreated('school_exam', 'exam-1', 'evt_1', ['applicationSlug' => 'from-message']));
    }

    public function testSchoolExamDeleteInvalidatesExamAndClassCachesWithMessageContextSlug(): void
    {
        $cacheInvalidationService = $this->createMock(CacheInvalidationService::class);
        $cacheInvalidationService->expects(self::once())
            ->method('invalidateSchoolExamListCaches')
            ->with('campus-beta');
        $cacheInvalidationService->expects(self::once())
            ->method('invalidateSchoolClassListCaches')
            ->with('campus-beta');

        $elastic = $this->createMock(ElasticsearchServiceInterface::class);
        $elastic->expects(self::once())->method('delete');

        $handler = $this->buildHandler(
            cacheInvalidationService: $cacheInvalidationService,
            elasticsearchService: $elastic,
        );

        $handler(new EntityDeleted('school_exam', 'exam-1', 'evt_2', ['applicationSlug' => 'campus-beta']));
    }

    public function testSchoolClassPatchInvalidatesOnlyScopedSchoolCaches(): void
    {
        $capturedExamSlugs = [];
        $capturedClassSlugs = [];

        $cacheInvalidationService = $this->createMock(CacheInvalidationService::class);
        $cacheInvalidationService->expects(self::exactly(2))
            ->method('invalidateSchoolExamListCaches')
            ->willReturnCallback(static function (?string $applicationSlug) use (&$capturedExamSlugs): void {
                $capturedExamSlugs[] = $applicationSlug;
            });
        $cacheInvalidationService->expects(self::exactly(2))
            ->method('invalidateSchoolClassListCaches')
            ->willReturnCallback(static function (?string $applicationSlug) use (&$capturedClassSlugs): void {
                $capturedClassSlugs[] = $applicationSlug;
            });

        $handler = $this->buildHandler(cacheInvalidationService: $cacheInvalidationService);

        $handler(new EntityPatched('school_class', 'class-1', 'evt_3', ['applicationSlug' => 'campus-alpha']));
        $handler(new EntityPatched('school_class', 'class-2', 'evt_4', ['applicationSlug' => 'campus-beta']));

        self::assertSame(['campus-alpha', 'campus-beta'], $capturedExamSlugs);
        self::assertSame(['campus-alpha', 'campus-beta'], $capturedClassSlugs);
    }

    private function buildHandler(
        ?ExamRepository $examRepository = null,
        ?CacheInvalidationService $cacheInvalidationService = null,
        ?ElasticsearchServiceInterface $elasticsearchService = null,
    ): EntityProjectionHandler {
        $guard = $this->createMock(MessageIdempotenceGuard::class);
        $guard->method('shouldProcess')->willReturn(true);

        return new EntityProjectionHandler(
            $this->createMock(ApplicationRepository::class),
            $this->createMock(JobRepository::class),
            $this->createMock(ProductRepository::class),
            $this->createMock(TaskRepository::class),
            $examRepository ?? $this->createMock(ExamRepository::class),
            $cacheInvalidationService ?? $this->createMock(CacheInvalidationService::class),
            $this->createMock(CriticalViewWarmer::class),
            $elasticsearchService ?? $this->createMock(ElasticsearchServiceInterface::class),
            $guard,
        );
    }

    private function buildExamWithApplicationSlug(string $applicationSlug): Exam
    {
        $application = (new Application())
            ->setTitle(str_replace('-', ' ', $applicationSlug))
            ->setDescription('Desc')
            ->ensureGeneratedSlug();

        $school = (new School())
            ->setName('School')
            ->setApplication($application);

        $class = (new SchoolClass())
            ->setName('Class A')
            ->setSchool($school);

        $teacher = (new Teacher())
            ->setName('Teacher A');

        return (new Exam())
            ->setTitle('Exam A')
            ->setSchoolClass($class)
            ->setTeacher($teacher);
    }
}
