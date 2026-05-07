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
use App\Recruit\Domain\Entity\Template;
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


    /** @var array<int, array<string, mixed>> */
    private const array TEMPLATES = [
        [
            'id' => 'tpl-001','name' => 'aside-structure-1','type' => 'resume','version' => 1,'layout' => 'aside','structure' => 'structure-1',
            'sections' => ['experience'=>'classic','education'=>'list','skills'=>'classic','languages'=>'dots','languagesLabel'=>'cards','certifications'=>'classic','references'=>'list','projects'=>'dot','interests'=>'dot','contact'=>'icons','profile'=>'classic'],
            'theme' => ['palette'=>['primary'=>'#1D4ED8','secondary'=>'#93C5FD','text'=>'#0F172A','muted'=>'#64748B','pageBackground'=>'#F8FAFC'],'line'=>'soft','density'=>'comfortable','textStyle'=>'roman','showIcon'=>true],
            'aside' => ['width'=>'720','height'=>'220px','radius'=>'18px'],
            'photo' => ['position'=>'left','size'=>'92px','shape'=>'circle','border'=>'2px solid #1F2937','photoType'=>'circle','photoSize'=>'92px','photoBorderRadius'=>'999px','photoBorderColor'=>'#1F2937'],
            'decor' => ['corners'=>[['shape'=>'circle','size'=>'280px','color'=>'#93C5FD','x'=>'top-right','y'=>'0'],['shape'=>'square','size'=>'220px','color'=>'#1D4ED8','x'=>'bottom-left','y'=>'0']]],
            'layoutOptions' => ['asideStartsAtTop'=>true],'decorOptions'=>['enabled'=>true,'preset'=>'geo-duo'],'sectionTitleStyle'=>['underline'=>'thin'],'headerType'=>'header-left',
        ],
        [
            'id' => 'tpl-002','name' => 'aside-structure-2','type' => 'resume','version' => 1,'layout' => 'aside','structure' => 'structure-2',
            'sections' => ['experience'=>'list','education'=>'dot','skills'=>'stars','languages'=>'progress-line','languagesLabel'=>'cards','certifications'=>'list','references'=>'dot','projects'=>'timeline','interests'=>'cards','contact'=>'icons','profile'=>'classic'],
            'theme' => ['palette'=>['primary'=>'#7C3AED','secondary'=>'#C4B5FD','text'=>'#1F2937','muted'=>'#6B7280','pageBackground'=>'#FAF5FF'],'line'=>'strong','density'=>'compact','textStyle'=>'roman','showIcon'=>true],
            'aside' => ['width'=>'720','height'=>'220px','radius'=>'18px'],'layoutOptions' => ['asideStartsAtTop'=>true],'decorOptions'=>['enabled'=>true,'preset'=>'abstract-duo'],'sectionTitleStyle'=>['underline'=>'thick'],'headerType'=>'header-right',
        ],
        [
            'id'=>'cpage-001','name'=>'cover-page-hero-01','type'=>'cover_page','version'=>1,'layout'=>'layout-left','structure'=>'cover-structure-1',
            'theme'=>['palette'=>['primary'=>'#0F4C81','secondary'=>'#5FA8D3','text'=>'#111827','muted'=>'#6B7280','pageBackground'=>'#F8FAFC'],'density'=>'airy','textStyle'=>'sans'],
            'sections'=>['hero'=>['alignment'=>'left','showPhoto'=>true,'accent'=>'bar','accentIntensity'=>'medium','photoPosition'=>'left']],
            'layoutOptions'=>['sectionSpacing'=>'normal','titleCase'=>'upper','contentWidth'=>'normal','photoShape'=>'rounded'],
            'decorOptions'=>['designTokens'=>['borderRadius'=>'sm','shadowDepth'=>'soft','gradientStyle'=>'linear-soft','patternOverlay'=>'dots','typographyScale'=>'balanced']],
        ],
        [
            'id'=>'cpage-002','name'=>'cover-page-editorial-02','type'=>'cover_page','version'=>1,'layout'=>'layout-left','structure'=>'cover-structure-2',
            'theme'=>['palette'=>['primary'=>'#7C3AED','secondary'=>'#C4B5FD','text'=>'#111827','muted'=>'#6B7280','pageBackground'=>'#FAF5FF'],'density'=>'comfortable','textStyle'=>'roman'],
            'sections'=>['hero'=>['alignment'=>'center','showPhoto'=>false,'accent'=>'shape','accentIntensity'=>'bold','photoPosition'=>'right']],
            'layoutOptions'=>['sectionSpacing'=>'relaxed','titleCase'=>'normal','contentWidth'=>'wide','photoShape'=>'square'],
            'decorOptions'=>['designTokens'=>['borderRadius'=>'md','shadowDepth'=>'medium','gradientStyle'=>'linear-vivid','patternOverlay'=>'grid','typographyScale'=>'spacious']],
        ],
        [
            'id'=>'cletter-001','name'=>'cover-letter-classic-01','type'=>'cover_letter','version'=>1,'layout'=>'layout-left','structure'=>'letter-structure-1',
            'theme'=>['palette'=>['primary'=>'#BE123C','secondary'=>'#FDA4AF','text'=>'#0F172A','muted'=>'#64748B','pageBackground'=>'#FFF1F2'],'density'=>'comfortable','textStyle'=>'roman'],
            'sections'=>['header'=>'minimal','body'=>'narrative','signature'=>'simple','calloutStyle'=>'quote','photoPosition'=>'left'],
            'layoutOptions'=>['paragraphSpacing'=>'normal','showDivider'=>true,'signatureAlign'=>'center','headerAlignment'=>'center'],
        ],
        [
            'id'=>'cletter-002','name'=>'cover-letter-modern-02','type'=>'cover_letter','version'=>1,'layout'=>'layout-left','structure'=>'letter-structure-2',
            'theme'=>['palette'=>['primary'=>'#065F46','secondary'=>'#6EE7B7','text'=>'#0F172A','muted'=>'#64748B','pageBackground'=>'#ECFDF5'],'density'=>'compact','textStyle'=>'sans'],
            'sections'=>['header'=>'detailed','body'=>'bullet-mix','signature'=>'formal','calloutStyle'=>'highlight-box','photoPosition'=>'right'],
            'layoutOptions'=>['paragraphSpacing'=>'wide','showDivider'=>false,'signatureAlign'=>'right','headerAlignment'=>'left'],
        ],
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

        $this->createTemplates($manager);

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


    private function createTemplates(ObjectManager $manager): void
    {
        foreach (self::TEMPLATES as $item) {
            $template = (new Template())
                ->setName($item['name'])
                ->setType($item['type'])
                ->setVersion($item['version'])
                ->setLayout($item['layout'])
                ->setStructure($item['structure'])
                ->setSections($item['sections'] ?? null)
                ->setTheme($item['theme'] ?? null)
                ->setAside($item['aside'] ?? null)
                ->setPhoto($item['photo'] ?? null)
                ->setDecor($item['decor'] ?? null)
                ->setLayoutOptions($item['layoutOptions'] ?? null)
                ->setDecorOptions($item['decorOptions'] ?? null)
                ->setSectionTitleStyle($item['sectionTitleStyle'] ?? null)
                ->setHeaderType($item['headerType'] ?? null);

            $manager->persist($template);
            $this->addReference('Recruit-Template-' . $item['id'], $template);
        }
    }

    #[Override]
    public function getOrder(): int
    {
        return 8;
    }
}
