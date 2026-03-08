<?php

declare(strict_types=1);

namespace App\Recruit\Infrastructure\DataFixtures\ORM;

use App\Platform\Domain\Entity\Application;
use App\Recruit\Domain\Entity\Badge;
use App\Recruit\Domain\Entity\Company;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use App\Recruit\Domain\Entity\Salary;
use App\Recruit\Domain\Entity\Tag;
use App\Recruit\Domain\Enum\ContractType;
use App\Recruit\Domain\Enum\Schedule;
use App\Recruit\Domain\Enum\WorkMode;
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

    /** @var array<int, string> */
    private const array RECRUIT_APPLICATION_KEYS = [
        'recruit-talent-hub',
        'recruit-hiring-pipeline',
        'recruit-interview-desk',
    ];

    /** @var array<int, array{name: string, logo: string, sector: string, size: string}> */
    private const array COMPANIES = [
        ['name' => 'Aveyara Software GmbH', 'logo' => 'AS', 'sector' => 'IT & Tech', 'size' => '11-50 Mitarbeiter'],
        ['name' => 'Bro Digital Studio', 'logo' => 'BD', 'sector' => 'Digital', 'size' => '51-200 Mitarbeiter'],
        ['name' => 'Nordic Cloud Systems', 'logo' => 'NC', 'sector' => 'Cloud', 'size' => '201-500 Mitarbeiter'],
        ['name' => 'FinOps Next', 'logo' => 'FN', 'sector' => 'FinTech', 'size' => '11-50 Mitarbeiter'],
        ['name' => 'Green Mobility Tech', 'logo' => 'GM', 'sector' => 'Mobility', 'size' => '51-200 Mitarbeiter'],
    ];

    /** @var array<int, string> */
    private const array TAGS = [
        'PHP', 'Laravel', 'React', 'Vue.js', 'Typescript', 'PostgreSQL', 'Docker', 'Kubernetes', 'AWS', 'CI/CD',
    ];

    /** @var array<int, string> */
    private const array BADGES = [
        'Bewerbungs-Update verfügbar',
        'Sei einer der ersten Bewerber',
        'Schnelle Bewerbung',
        'Top Arbeitgeber',
        'Remote Friendly',
    ];

    /** @var array<int, string> */
    private const array LOCATIONS = ['Stuttgart', 'Berlin', 'Hamburg', 'München', 'Köln', 'Frankfurt'];

    /** @var array<int, string> */
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

        $tags = [];
        foreach (self::TAGS as $item) {
            $tag = (new Tag())->setLabel($item);
            $manager->persist($tag);
            $tags[] = $tag;
        }

        $badges = [];
        foreach (self::BADGES as $item) {
            $badge = (new Badge())->setLabel($item);
            $manager->persist($badge);
            $badges[] = $badge;
        }

        $jobReferenceIndex = 1;
        foreach (self::RECRUIT_APPLICATION_KEYS as $applicationKeyIndex => $applicationKey) {
            /** @var Application $application */
            $application = $this->getReference('Recruit-Application-' . $applicationKey, Application::class);

            $recruit = $manager->getRepository(Recruit::class)->findOneBy([
                'application' => $application,
            ]);

            if (!$recruit instanceof Recruit) {
                $recruit = (new Recruit())->setApplication($application);
                $manager->persist($recruit);
            }

            for ($i = 1; $i <= self::JOB_COUNT_PER_APPLICATION; ++$i) {
                $loopIndex = ($applicationKeyIndex * self::JOB_COUNT_PER_APPLICATION) + $i;
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

                $tagStart = (int) floor($loopIndex % count($tags));
                for ($offset = 0; $offset < 4; ++$offset) {
                    $job->addTag($tags[($tagStart + $offset) % count($tags)]);
                }

                $manager->persist($salary);
                $manager->persist($job);
                $this->addReference(sprintf('Recruit-Job-%03d', $jobReferenceIndex), $job);

                ++$jobReferenceIndex;
            }
        }

        $manager->flush();
    }

    #[Override]
    public function getOrder(): int
    {
        return 8;
    }
}
