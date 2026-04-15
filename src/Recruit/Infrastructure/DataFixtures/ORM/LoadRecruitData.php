<?php

declare(strict_types=1);

namespace App\Recruit\Infrastructure\DataFixtures\ORM;

use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Enum\PlatformKey;
use App\Recruit\Domain\Entity\Badge;
use App\Recruit\Domain\Entity\Company;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use App\Recruit\Domain\Entity\Salary;
use App\Recruit\Domain\Entity\Tag;
use App\Recruit\Domain\Enum\ContractType;
use App\Recruit\Domain\Enum\ExperienceLevel;
use App\Recruit\Domain\Enum\Schedule;
use App\Recruit\Domain\Enum\WorkMode;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

use function count;
use function floor;
use function sprintf;

final class LoadRecruitData extends Fixture implements OrderedFixtureInterface
{
    private const int JOB_COUNT_PER_APPLICATION = 12;
    private const string GENERAL_APPLICATION_KEY = 'recruit-general-core';

    /**
     * @var array<int, array{name: string, logo: string, sector: string, size: string}>
     */
    private const array COMPANIES = [
        [
            'name' => 'Aveyara Software GmbH',
            'logo' => 'AS',
            'sector' => 'IT & Tech',
            'size' => '11-50 Mitarbeiter',
        ],
        [
            'name' => 'Bro Digital Studio',
            'logo' => 'BD',
            'sector' => 'Digital',
            'size' => '51-200 Mitarbeiter',
        ],
        [
            'name' => 'Nordic Cloud Systems',
            'logo' => 'NC',
            'sector' => 'Cloud',
            'size' => '201-500 Mitarbeiter',
        ],
        [
            'name' => 'FinOps Next',
            'logo' => 'FN',
            'sector' => 'FinTech',
            'size' => '11-50 Mitarbeiter',
        ],
        [
            'name' => 'Green Mobility Tech',
            'logo' => 'GM',
            'sector' => 'Mobility',
            'size' => '51-200 Mitarbeiter',
        ],
    ];

    /**
     * @var array<int, string>
     */
    private const array TAGS = [
        'PHP', 'Laravel', 'React', 'Vue.js', 'Typescript', 'PostgreSQL', 'Docker', 'Kubernetes', 'AWS', 'CI/CD',
    ];

    /**
     * @var array<int, string>
     */
    private const array BADGES = [
        'Bewerbungs-Update verfügbar',
        'Sei einer der ersten Bewerber',
        'Schnelle Bewerbung',
        'Top Arbeitgeber',
        'Remote Friendly',
    ];

    /**
     * @var array<int, string>
     */
    private const array LOCATIONS = ['Stuttgart', 'Berlin', 'Hamburg', 'München', 'Köln', 'Frankfurt'];

    /**
     * @var array<int, string>
     */
    private const array TITLES = [
        'Senior Full Stack Entwickler (m/w/d) - PHP/Laravel, React/Vue.js/Typescript',
        'Backend Engineer (m/w/d) - Symfony & API Platform',
        'Frontend Engineer (m/w/d) - React & TypeScript',
        'DevOps Engineer (m/w/d) - Docker, Kubernetes, AWS',
        'Technical Product Engineer (m/w/d) - SaaS Platform',
    ];

    #[Override]
    public function load(ObjectManager $manager): void
    {
        $generalOwner = $this->getReference('User-john-root', User::class);
        /** @var Application $application */
        $application = $this->getReference('Application-recruit-lite-app', Application::class);

        $recruit = $manager->getRepository(Recruit::class)->findOneBy([
            'application' => $application,
        ]);

        if (!$recruit instanceof Recruit) {
            $recruit = (new Recruit())->setApplication($application);
            $manager->persist($recruit);
        }

        $companies = [];
        foreach (self::COMPANIES as $item) {
            $company = (new Company())
                ->setName($item['name'])
                ->setLogo($item['logo'])
                ->setSector($item['sector'])
                ->setSize($item['size']);

            $manager->persist($company);
            $companies[] = $company;
        }
        if ($companies !== []) {
            $this->addReference('Recruit-Company-1', $companies[0]);
        }

        $tags = [];
        foreach (self::TAGS as $item) {
            $tag = (new Tag())->setLabel($item);
            $manager->persist($tag);
            $tags[] = $tag;
        }
        if ($tags !== []) {
            $this->addReference('Recruit-Tag-1', $tags[0]);
        }

        $badges = [];
        foreach (self::BADGES as $item) {
            $badge = (new Badge())->setLabel($item);
            $manager->persist($badge);
            $badges[] = $badge;
        }
        if ($badges !== []) {
            $this->addReference('Recruit-Badge-1', $badges[0]);
        }

        $recruitApplications = $manager->getRepository(Application::class)
            ->createQueryBuilder('application')
            ->innerJoin('application.platform', 'platform')
            ->andWhere('platform.platformKey = :platformKey')
            ->setParameter('platformKey', PlatformKey::RECRUIT)
            ->orderBy('application.title', 'ASC')
            ->getQuery()
            ->getResult();

        $jobReferenceIndex = 1;
        foreach ($recruitApplications as $applicationIndex => $application) {
            if (!$application instanceof Application) {
                continue;
            }

            $recruit = $manager->getRepository(Recruit::class)->findOneBy([
                'application' => $application,
            ]);

            if (!$recruit instanceof Recruit) {
                $recruit = (new Recruit())->setApplication($application);
                $manager->persist($recruit);
            }
            $applicationKey = $application->getSlug();
            $this->addReference('Recruit-' . $applicationKey, $recruit);
            if ($applicationKey === self::GENERAL_APPLICATION_KEY) {
                $this->addReference('Recruit-General-Core', $recruit);
            }

            for ($i = 1; $i <= self::JOB_COUNT_PER_APPLICATION; $i++) {
                $loopIndex = ($applicationIndex * self::JOB_COUNT_PER_APPLICATION) + $i;
                $title = self::TITLES[($loopIndex - 1) % count(self::TITLES)];
                $company = $companies[($loopIndex - 1) % count($companies)];

                $salaryMin = 38000 + (($loopIndex % 8) * 4000);
                $salary = (new Salary())
                    ->setMin($salaryMin)
                    ->setMax($salaryMin + 18000)
                    ->setCurrency('EUR')
                    ->setPeriod('year');

                $job = (new Job())
                    ->setRecruit($recruit)
                    ->setOwner($applicationKey === self::GENERAL_APPLICATION_KEY && $generalOwner instanceof User ? $generalOwner : $application->getUser())
                    ->setTitle($title)
                    ->setCompany($company)
                    ->setSalary($salary)
                    ->setLocation(self::LOCATIONS[($loopIndex - 1) % count(self::LOCATIONS)])
                    ->setContractType(match ($loopIndex % 4) {
                        0 => ContractType::CDI,
                        1 => ContractType::CDD,
                        2 => ContractType::FREELANCE,
                        default => ContractType::INTERNSHIP,
                    })
                    ->setWorkMode(match ($loopIndex % 3) {
                        0 => WorkMode::HYBRID,
                        1 => WorkMode::REMOTE,
                        default => WorkMode::ONSITE,
                    })
                    ->setSchedule(match ($loopIndex % 3) {
                        0 => Schedule::FULL_TIME,
                        1 => Schedule::PART_TIME,
                        default => Schedule::CONTRACT,
                    })
                    ->setExperienceLevel(match ($loopIndex % 4) {
                        0 => ExperienceLevel::JUNIOR,
                        1 => ExperienceLevel::MID,
                        2 => ExperienceLevel::SENIOR,
                        default => ExperienceLevel::LEAD,
                    })
                    ->setYearsExperienceMin(($loopIndex - 1) % 6)
                    ->setYearsExperienceMax((($loopIndex - 1) % 6) + 3)
                    ->setIsPublished($loopIndex % 5 !== 0)
                    ->setSummary('Wir suchen einen leidenschaftlichen Fullstack-Entwickler mit Expertise in PHP, Laravel, React und Typescript.')
                    ->setMatchScore(65 + ($loopIndex % 35))
                    ->setMissionTitle('Deine Mission:')
                    ->setMissionDescription('Wir suchen einen leidenschaftlichen Fullstack-Entwickler...')
                    ->setResponsibilities([
                        'Innovation gestalten',
                        'Architektur weiterentwickeln',
                        sprintf('Agilität leben (Team %d)', (($loopIndex - 1) % 10) + 1),
                    ])
                    ->setProfile([
                        'Erfahrung in PHP und Laravel',
                        'REST API und PostgreSQL',
                        'Deutsch und Englisch',
                    ])
                    ->setBenefits([
                        'Sinnstiftende Arbeit',
                        'Flexibles Arbeiten',
                        'Weiterbildung',
                    ])
                    ->setCreatedAt((new DateTimeImmutable())->modify(sprintf('-%d day', $loopIndex % 45)))
                    ->ensureGeneratedSlug();

                $job->addBadge($badges[($loopIndex - 1) % count($badges)]);
                $job->addBadge($badges[$loopIndex % count($badges)]);

                $tagStart = (int)floor($loopIndex % count($tags));
                for ($offset = 0; $offset < 4; $offset++) {
                    $job->addTag($tags[($tagStart + $offset) % count($tags)]);
                }

                $manager->persist($salary);
                $manager->persist($job);
                $this->addReference(sprintf('Recruit-Job-%03d', $jobReferenceIndex), $job);
                if ($i === 1) {
                    $this->addReference('Recruit-Job-' . $applicationKey . '-1', $job);
                    if ($applicationKey === self::GENERAL_APPLICATION_KEY) {
                        $this->addReference('Recruit-Job-General-Core-1', $job);
                    }
                }

                $jobReferenceIndex++;
            }
        }

        if ($generalOwner instanceof User) {
            $this->addReference('Recruit-General-Owner', $generalOwner);
        }

        $manager->flush();
    }

    #[Override]
    public function getOrder(): int
    {
        return 8;
    }
}
