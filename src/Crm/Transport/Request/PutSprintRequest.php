<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use App\Crm\Domain\Enum\SprintStatus;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class PutSprintRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    #[Assert\Length(max: 5000)]
    public ?string $goal = null;

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [self::class, 'statusChoices'])]
    public ?string $status = null;

    #[Assert\DateTime]
    public ?string $startDate = null;

    #[Assert\DateTime]
    public ?string $endDate = null;

    public bool $hasName = false;
    public bool $hasGoal = false;
    public bool $hasStatus = false;
    public bool $hasStartDate = false;
    public bool $hasEndDate = false;

    public static function fromArray(array $payload): self
    {
        $request = new self();

        foreach (['name', 'goal', 'status', 'startDate', 'endDate'] as $field) {
            $flag = 'has' . ucfirst($field);
            if (!array_key_exists($field, $payload)) {
                continue;
            }

            $request->{$flag} = true;
            $request->{$field} = $payload[$field] !== null ? (string)$payload[$field] : null;
        }

        return $request;
    }

    #[Assert\Callback]
    public function validatePutReplacement(ExecutionContextInterface $context): void
    {
        foreach (['name', 'goal', 'status', 'startDate', 'endDate'] as $field) {
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
        return array_map(static fn (SprintStatus $status): string => $status->value, SprintStatus::cases());
    }
}
