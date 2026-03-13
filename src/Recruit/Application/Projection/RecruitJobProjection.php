<?php

declare(strict_types=1);

namespace App\Recruit\Application\Projection;

final class RecruitJobProjection
{
    final public const string INDEX_NAME = 'recruit_job_v1';

    /**
     * @return array<string, mixed>
     */
    public static function mapping(): array
    {
        return [
            'properties' => [
                'id' => [
                    'type' => 'keyword',
                ],
                'slug' => [
                    'type' => 'keyword',
                ],
                'title' => [
                    'type' => 'text',
                ],
                'summary' => [
                    'type' => 'text',
                ],
                'location' => [
                    'type' => 'text',
                ],
                'contractType' => [
                    'type' => 'keyword',
                ],
                'workMode' => [
                    'type' => 'keyword',
                ],
                'schedule' => [
                    'type' => 'keyword',
                ],
                'experienceLevel' => [
                    'type' => 'keyword',
                ],
                'yearsExperienceMin' => [
                    'type' => 'integer',
                ],
                'yearsExperienceMax' => [
                    'type' => 'integer',
                ],
                'tags' => [
                    'type' => 'keyword',
                ],
                'applicationSlug' => [
                    'type' => 'keyword',
                ],
                'updatedAt' => [
                    'type' => 'date',
                ],
            ],
        ];
    }
}
