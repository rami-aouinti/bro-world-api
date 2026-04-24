<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\DataFixtures\ORM;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\BlogComment;
use App\Blog\Domain\Entity\BlogPost;
use App\Blog\Domain\Entity\BlogReaction;
use App\Blog\Domain\Entity\BlogTag;
use App\Blog\Domain\Enum\BlogReactionType;
use App\Blog\Domain\Enum\BlogType;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Entity\Plugin;
use App\User\Domain\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

use function array_key_exists;
use function count;
use function sprintf;

final class LoadBlogData extends Fixture implements OrderedFixtureInterface
{
    #[Override]
    public function load(ObjectManager $manager): void
    {
        $johnRoot = $this->getReference('User-john-root', User::class);
        $johnAdmin = $this->getReference('User-john-admin', User::class);
        $johnUser = $this->getReference('User-john-user', User::class);
        $blogPlugin = $this->getReference('Plugin-Knowledge-Base-Connector', Plugin::class);

        $applications = $manager->getRepository(Application::class)
            ->createQueryBuilder('application')
            ->innerJoin('application.applicationPlugins', 'applicationPlugin')
            ->andWhere('applicationPlugin.plugin = :plugin')
            ->setParameter('plugin', $blogPlugin)
            ->orderBy('application.title', 'ASC')
            ->getQuery()
            ->getResult();

        $generalBlog = (new Blog())
            ->setTitle('General Blog Root')
            ->setSlug('general')
            ->setDescription('Blog communautaire global pour toute la plateforme.')
            ->setOwner($johnRoot)
            ->setType(BlogType::GENERAL);
        $manager->persist($generalBlog);

        /** @var list<Blog> $blogs */
        $blogs = [$generalBlog];
        foreach ($applications as $index => $application) {
            if (!$application instanceof Application) {
                continue;
            }

            $blog = (new Blog())
                ->setTitle(sprintf('Application Blog %d', $index + 1))
                ->setSlug(sprintf('application-blog-%d', $index + 1))
                ->setDescription(sprintf('Espace communautaire pour %s', $application->getTitle()))
                ->setOwner($johnRoot)
                ->setType(BlogType::APPLICATION)
                ->setApplication($application);
            $manager->persist($blog);
            $blogs[] = $blog;
        }

        $authors = [$johnRoot, $johnAdmin, $johnUser];
        $reactionTypes = [BlogReactionType::LIKE, BlogReactionType::HEART, BlogReactionType::LAUGH, BlogReactionType::CELEBRATE];

        foreach ($blogs as $blogIndex => $blog) {
            $generalPosts = $blog->getType() === BlogType::GENERAL ? $this->getGeneralBlogPosts() : [];
            $postCount = $blog->getType() === BlogType::GENERAL ? count($generalPosts) : 5;

            for ($postIndex = 1; $postIndex <= $postCount; $postIndex++) {
                $author = $authors[($blogIndex + $postIndex) % count($authors)];

                $postData = [];
                if ($blog->getType() === BlogType::GENERAL && array_key_exists($postIndex - 1, $generalPosts)) {
                    /** @var array{title:string,slug:string,description:string,metaTitle:string,metaDescription:string,mediaUrls:list<string>} $postData */
                    $postData = $generalPosts[$postIndex - 1];
                }

                $post = new BlogPost()
                    ->setBlog($blog)
                    ->setAuthor($author)
                    ->setTitle($postData['title'] ?? sprintf('Post fixture %d', $postIndex))
                    ->setSlug($postData['slug'] ?? sprintf('fixture-%d-%d-root', $blogIndex + 1, $postIndex))
                    ->setContent($postData !== []
                        ? sprintf(
                            "Description:\n%s\n\nMeta Title: %s\nMeta Description: %s",
                            $postData['description'],
                            $postData['metaTitle'],
                            $postData['metaDescription'],
                        )
                        : sprintf('Fixture post %d for %s', $postIndex, $blog->getTitle()))
                    ->setIsPinned($postIndex === 1);

                if (array_key_exists('mediaUrls', $postData)) {
                    $post->setMediaUrls($postData['mediaUrls']);
                }

                if ($postData === [] && $postIndex % 5 === 0) {
                    $post
                        ->setSharedUrl(sprintf('https://example.com/shared/%d/%d', $blogIndex + 1, $postIndex))
                        ->setContent(null);
                }

                if ($postData === [] && $postIndex % 4 === 0) {
                    $post->setMediaUrls([
                        sprintf('https://cdn.example.com/blog/%d/%d-photo.jpg', $blogIndex + 1, $postIndex),
                        sprintf('https://cdn.example.com/blog/%d/%d-video.mp4', $blogIndex + 1, $postIndex),
                    ]);
                }

                $postTags = [];
                $tag = new BlogTag()
                    ->setBlog($blog)
                    ->setLabel('BroWorld');
                $manager->persist($tag);
                $postTags[] = $tag;

                $post->setTags($postTags);
                $manager->persist($post);

                if ($postIndex <= 2) {
                    $sharedChild = new BlogPost()
                        ->setBlog($blog)
                        ->setAuthor($authors[($blogIndex + $postIndex + 1) % count($authors)])
                        ->setTitle(sprintf('Shared fixture %d', $postIndex))
                        ->setSlug(sprintf('fixture-%d-%d-child', $blogIndex + 1, $postIndex))
                        ->setParentPost($post)
                        ->setSharedUrl(sprintf('https://example.com/original/%d/%d', $blogIndex + 1, $postIndex))
                        ->setContent(null)
                        ->setMediaUrls([
                            sprintf('https://cdn.example.com/blog/%d/%d-child-image.webp', $blogIndex + 1, $postIndex),
                        ]);
                    $manager->persist($sharedChild);
                }

                $parent = (new BlogComment())
                    ->setPost($post)
                    ->setAuthor($author)
                    ->setContent('Parent comment #' . $postIndex);
                $child = (new BlogComment())
                    ->setPost($post)
                    ->setAuthor($authors[($blogIndex + $postIndex + 1) % count($authors)])
                    ->setContent('Child comment #' . $postIndex)
                    ->setParent($parent);
                $subChild = (new BlogComment())
                    ->setPost($post)
                    ->setAuthor($authors[($blogIndex + $postIndex + 2) % count($authors)])
                    ->setContent('Sub child comment #' . $postIndex)
                    ->setParent($child);

                $manager->persist($parent);
                $manager->persist($child);
                $manager->persist($subChild);

                $manager->persist((new BlogReaction())
                    ->setComment($parent)
                    ->setAuthor($authors[($blogIndex + 1) % count($authors)])
                    ->setType($reactionTypes[($blogIndex + $postIndex) % count($reactionTypes)]));
            }
        }

        $manager->flush();
    }

    #[Override]
    public function getOrder(): int
    {
        return 41;
    }

    /**
     * @return list<array{
     *     title: string,
     *     slug: string,
     *     description: string,
     *     metaTitle: string,
     *     metaDescription: string,
     *     mediaUrls: list<string>
     * }>
     */
    private function getGeneralBlogPosts(): array
    {
        return [
            [
                'title' => 'Welcome to Bro World: Your Digital Experience Starts Here',
                'slug' => 'index',
                'description' => 'The Index page is the heartbeat of the platform. It gives users an instant overview of what matters most through smart shortcuts, highlighted content, and real-time activity. Designed as a dynamic starting point, it helps everyone move faster, discover more, and stay connected from the very first click.',
                'metaTitle' => 'Bro World Index Page | Smart Dashboard & Quick Access',
                'metaDescription' => 'Discover the Bro World Index page: a dynamic dashboard with quick shortcuts, activity highlights, and smooth navigation to every key platform section.',
                'mediaUrls' => ['https://bro-world.org/uploads/blog/general/blog1.png'],
            ],
            [
                'title' => 'Profile & Settings: Personalize, Protect, and Control Your Space',
                'slug' => 'profile-setting',
                'description' => 'The Profile/Setting page is where identity meets control. Users can update personal information, manage privacy options, and fine-tune account preferences in one streamlined interface. From visual customization to security settings, every component is built to deliver a safer, more personal platform experience.',
                'metaTitle' => 'Profile & Settings in Bro World | Personalization & Security',
                'metaDescription' => 'Manage your Bro World profile and settings in one place. Update personal info, privacy options, notifications, and account security with ease.',
                'mediaUrls' => ['https://bro-world.org/uploads/blog/general/blog2.png'],
            ],
            [
                'title' => 'Inbox: Seamless Conversations, All in One Place',
                'slug' => 'inbox',
                'description' => 'The Inbox page is built for smooth, focused communication. It centralizes private conversations with clean message threads, unread indicators, and quick-reply interactions. Whether for social exchange or team coordination, this page keeps communication organized, responsive, and always within reach.',
                'metaTitle' => 'Bro World Inbox | Real-Time Messaging & Conversation Threads',
                'metaDescription' => 'Stay connected with the Bro World Inbox. Read, send, and manage private conversations using organized threads, unread markers, and fast replies.',
                'mediaUrls' => ['https://bro-world.org/uploads/blog/general/blog13.png'],
            ],
            [
                'title' => 'Calendar: Plan Smarter, Organize Better, Never Miss a Moment',
                'slug' => 'calendar',
                'description' => 'The Calendar page transforms scheduling into a clear visual experience. Users can track events, manage reminders, and navigate daily, weekly, or monthly plans with ease. By turning scattered tasks into structured timelines, it helps users stay productive and fully in control of their time.',
                'metaTitle' => 'Bro World Calendar | Event Planning, Reminders & Scheduling',
                'metaDescription' => 'Organize your schedule with the Bro World Calendar. Track events, set reminders, and plan tasks across daily, weekly, and monthly views.',
                'mediaUrls' => ['https://bro-world.org/uploads/blog/general/blog14.png'],
            ],
            [
                'title' => 'Platform Overview: Explore the Core of Bro World',
                'slug' => 'platform',
                'description' => 'The Platform page provides a complete overview of the ecosystem and its main modules. It helps users understand how features connect, where to navigate next, and how to maximize their workflow. Acting as a central map of the product, this page improves orientation and overall usability.',
                'metaTitle' => 'Bro World Platform Overview | Modules, Navigation & Ecosystem',
                'metaDescription' => 'Explore the Bro World platform overview page to understand core modules, feature connections, and efficient navigation across the full ecosystem.',
                'mediaUrls' => ['https://bro-world.org/uploads/blog/general/blog6.png'],
            ],
            [
                'title' => 'CRM Hub: Build Stronger Relationships, Drive Better Results',
                'slug' => 'crm',
                'description' => 'The CRM page is designed to support customer lifecycle management from first contact to long-term engagement. Users can track leads, manage interactions, and monitor progress through structured pipelines. It turns relationship data into actionable insights that help teams close smarter and grow faster.',
                'metaTitle' => 'Bro World CRM | Lead Tracking & Customer Relationship Management',
                'metaDescription' => 'Use Bro World CRM to manage contacts, monitor leads, track interactions, and improve sales performance through a structured customer pipeline.',
                'mediaUrls' => ['https://bro-world.org/uploads/blog/general/blog3.png'],
            ],
            [
                'title' => 'Shop Experience: Discover, Select, and Purchase with Confidence',
                'slug' => 'shop',
                'description' => 'The Shop page delivers an intuitive e-commerce journey inside the platform. With product categories, detailed listings, filters, and featured items, users can browse efficiently and make informed decisions. The experience is optimized for clarity, speed, and conversion from discovery to checkout.',
                'metaTitle' => 'Bro World Shop | Product Discovery & Seamless Checkout',
                'metaDescription' => 'Browse the Bro World Shop for a smooth e-commerce experience with product categories, filters, detailed listings, and a fast purchase flow.',
                'mediaUrls' => ['https://bro-world.org/uploads/blog/general/blog4.png'],
            ],
            [
                'title' => 'Job Board: Connect Talent with Opportunity',
                'slug' => 'job',
                'description' => 'The Job page creates a focused environment for recruitment and career growth. Candidates can explore openings, review requirements, and apply directly through a structured interface. Recruiters can present roles clearly and attract relevant profiles, making hiring and job discovery more effective for everyone.',
                'metaTitle' => 'Bro World Job Board | Career Opportunities & Easy Applications',
                'metaDescription' => 'Find jobs on Bro World with structured listings, role details, and direct applications. Connect talent and recruiters in one modern hiring space.',
                'mediaUrls' => ['https://bro-world.org/uploads/blog/general/blog5.png'],
            ],
            [
                'title' => 'Games Zone: Interactive Fun That Powers Engagement',
                'slug' => 'games',
                'description' => 'The Games page adds a social entertainment layer to the platform. Users can access mini-games, challenge others, and track performance through score-driven components. Designed for quick interaction and repeat play, this page boosts engagement while creating a lively, community-oriented experience.',
                'metaTitle' => 'Bro World Games | Mini-Games, Scores & Social Competition',
                'metaDescription' => 'Enter the Bro World Games zone to play mini-games, challenge others, and track rankings in a fun, interactive, and community-driven environment.',
                'mediaUrls' => [
                    'https://bro-world.org/uploads/blog/general/blog7.png',
                    'https://bro-world.org/uploads/blog/general/blog8.png',
                ],
            ],
            [
                'title' => 'Quiz Center: Learn, Compete, and Improve in Real Time',
                'slug' => 'quiz',
                'description' => 'The Quiz page offers interactive knowledge challenges across multiple topics. Users can answer timed questions, receive instant feedback, and monitor their results after each session. Combining education with gamification, this module encourages continuous learning through engaging, measurable progress.',
                'metaTitle' => 'Bro World Quiz Center | Knowledge Challenges & Instant Results',
                'metaDescription' => 'Test your knowledge in the Bro World Quiz Center with timed questions, topic categories, instant scoring, and progress-focused feedback.',
                'mediaUrls' => ['https://bro-world.org/uploads/blog/general/blog9.png'],
            ],
            [
                'title' => 'Football Hub: Matches, Stats, and Fan-First Insights',
                'slug' => 'sport-football',
                'description' => 'The Football page is a dedicated space for match-focused sports content. Users can follow fixtures, check results, and explore team or player statistics in one unified interface. Built for both quick updates and deeper analysis, it delivers a complete football experience for every type of fan.',
                'metaTitle' => 'Bro World Football Hub | Fixtures, Results & Team Statistics',
                'metaDescription' => 'Follow football on Bro World with match schedules, live or recent results, team performance data, and player stats in one dedicated hub.',
                'mediaUrls' => [
                    'https://bro-world.org/uploads/blog/general/blog10.png',
                    'https://bro-world.org/uploads/blog/general/blog11.png',
                    'https://bro-world.org/uploads/blog/general/blog12.png',
                ],
            ],
        ];
    }
}
