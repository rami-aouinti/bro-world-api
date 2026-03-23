<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use App\Crm\Domain\Enum\ProjectStatus;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class PutProjectRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    #[Assert\Length(max: 64)]
    public ?string $code = null;

    #[Assert\Length(max: 5000)]
    public ?string $description = null;

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [self::class, 'statusChoices'])]
    public ?string $status = null;

    #[Assert\DateTime]
    public ?string $startedAt = null;

    #[Assert\DateTime]
    public ?string $dueAt = null;

    #[Assert\Length(max: 255)]
    public ?string $githubToken = null;

    #[Assert\Type(type: 'array')]
    public ?array $githubRepositories = null;

    public bool $hasName = false;
    public bool $hasCode = false;
    public bool $hasDescription = false;
    public bool $hasStatus = false;
    public bool $hasStartedAt = false;
    public bool $hasDueAt = false;
    public bool $hasGithubToken = false;
    public bool $hasGithubRepositories = false;

    public static function fromArray(array $payload): self
    {
        $request = new self();

        foreach ([
            'name',
            'code',
            'description',
            'status',
            'startedAt',
            'dueAt',
            'githubToken',
            'githubRepositories',
        ] as $field) {
            $flag = 'has' . ucfirst($field);
            if (!array_key_exists($field, $payload)) {
                continue;
            }

            $request->{$flag} = true;

            if ($field === 'githubRepositories') {
                $request->githubRepositories = is_array($payload[$field]) ? $payload[$field] : null;

                continue;
            }

            $request->{$field} = $payload[$field] !== null ? (string)$payload[$field] : null;
        }

        return $request;
    }

    #[Assert\Callback]
    public function validatePutReplacement(ExecutionContextInterface $context): void
    {
        foreach ([
            'name',
            'code',
            'description',
            'status',
            'startedAt',
            'dueAt',
            'githubToken',
            'githubRepositories',
        ] as $field) {
            $flag = 'has' . ucfirst($field);
            if ($this->{$flag}) {
                continue;
            }

            $context->buildViolation('This field is required for PUT replacement.')
                ->atPath($field)
                ->addViolation();
        }
    }

    /**
     * @return list<string>
     */
    public static function statusChoices(): array
    {
        return array_map(static fn (ProjectStatus $status): string => $status->value, ProjectStatus::cases());
    }
}
