<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Job;

use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Enum\ContractType;
use App\Recruit\Domain\Enum\ExperienceLevel;
use App\Recruit\Domain\Enum\Schedule;
use App\Recruit\Domain\Enum\WorkMode;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function sprintf;
use function trim;

trait JobPayloadHydratorTrait
{
    /**
     * @param array<string, mixed> $payload
     */
    private function applyJobFields(Job $job, array $payload, bool $allowTitleEmpty = false): void
    {
        $title = $payload['title'] ?? null;
        if ($title !== null) {
            if (!is_string($title) || (!$allowTitleEmpty && trim($title) === '')) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "title" must be a non-empty string.');
            }

            $job->setTitle(trim($title));
        }

        $this->applyString($payload, 'location', static fn (string $value): Job => $job->setLocation($value));
        $this->applyString($payload, 'summary', static fn (string $value): Job => $job->setSummary($value));
        $this->applyString($payload, 'missionTitle', static fn (string $value): Job => $job->setMissionTitle($value));
        $this->applyString($payload, 'missionDescription', static fn (string $value): Job => $job->setMissionDescription($value));

        $this->applyEnumString($payload, 'contractType', ContractType::class, static fn (string $value): Job => $job->setContractType($value));
        $this->applyEnumString($payload, 'workMode', WorkMode::class, static fn (string $value): Job => $job->setWorkMode($value));
        $this->applyEnumString($payload, 'schedule', Schedule::class, static fn (string $value): Job => $job->setSchedule($value));
        $this->applyEnumString($payload, 'experienceLevel', ExperienceLevel::class, static fn (string $value): Job => $job->setExperienceLevel($value));

        $this->applyInt($payload, 'yearsExperienceMin', static fn (int $value): Job => $job->setYearsExperienceMin($value));
        $this->applyInt($payload, 'yearsExperienceMax', static fn (int $value): Job => $job->setYearsExperienceMax($value));

        $this->applyArray($payload, 'responsibilities', static fn (array $value): Job => $job->setResponsibilities($value));
        $this->applyArray($payload, 'profile', static fn (array $value): Job => $job->setProfile($value));
        $this->applyArray($payload, 'benefits', static fn (array $value): Job => $job->setBenefits($value));

        $isPublished = $payload['isPublished'] ?? null;
        if ($isPublished !== null) {
            if (!is_bool($isPublished)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "isPublished" must be a boolean.');
            }

            $job->setIsPublished($isPublished);
        }
    }

    /** @param array<string, mixed> $payload */
    private function applyString(array $payload, string $key, callable $setter): void
    {
        $value = $payload[$key] ?? null;
        if ($value !== null) {
            if (!is_string($value)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, sprintf('Field "%s" must be a string.', $key));
            }

            $setter($value);
        }
    }

    /** @param array<string, mixed> $payload */
    private function applyInt(array $payload, string $key, callable $setter): void
    {
        $value = $payload[$key] ?? null;
        if ($value !== null) {
            if (!is_int($value)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, sprintf('Field "%s" must be an integer.', $key));
            }

            $setter($value);
        }
    }

    /** @param array<string, mixed> $payload */
    private function applyArray(array $payload, string $key, callable $setter): void
    {
        $value = $payload[$key] ?? null;
        if ($value !== null) {
            if (!is_array($value)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, sprintf('Field "%s" must be an array.', $key));
            }

            $setter($value);
        }
    }

    /** @param array<string, mixed> $payload */
    private function applyEnumString(array $payload, string $key, string $enumClass, callable $setter): void
    {
        $value = $payload[$key] ?? null;
        if ($value !== null) {
            if (!is_string($value) || $enumClass::tryFrom($value) === null) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, sprintf('Field "%s" has an invalid value.', $key));
            }

            $setter($value);
        }
    }
}
