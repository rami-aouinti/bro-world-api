<?php

declare(strict_types=1);

namespace App\Page\Infrastructure\DataFixtures\ORM;

use App\Page\Domain\Entity\About;
use App\Page\Domain\Entity\Contact;
use App\Page\Domain\Entity\Faq;
use App\Page\Domain\Entity\Home;
use App\Page\Domain\Entity\PageLanguage;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

final class LoadPageData extends Fixture implements OrderedFixtureInterface
{
    #[Override]
    public function load(ObjectManager $manager): void
    {
        $frLanguage = (new PageLanguage())->setCode('fr')->setLabel('Français');
        $enLanguage = (new PageLanguage())->setCode('en')->setLabel('English');
        $esLanguage = (new PageLanguage())->setCode('es')->setLabel('Español');
        $deLanguage = (new PageLanguage())->setCode('de')->setLabel('Deutsch');

        $manager->persist($frLanguage);
        $manager->persist($enLanguage);
        $manager->persist($esLanguage);
        $manager->persist($deLanguage);

        $this->persistPages($manager, $frLanguage, $this->getFrenchHome(), $this->getFrenchAbout(), $this->getFrenchContact(), $this->getFrenchFaq());
        $this->persistPages($manager, $enLanguage, $this->getEnglishHome(), $this->getEnglishAbout(), $this->getEnglishContact(), $this->getEnglishFaq());
        $this->persistPages($manager, $esLanguage, $this->getSpanishHome(), $this->getSpanishAbout(), $this->getSpanishContact(), $this->getSpanishFaq());
        $this->persistPages($manager, $deLanguage, $this->getGermanHome(), $this->getGermanAbout(), $this->getGermanContact(), $this->getGermanFaq());

        $manager->flush();
    }

    #[Override]
    public function getOrder(): int
    {
        return 10;
    }

    private function persistPages(ObjectManager $manager, PageLanguage $language, array $home, array $about, array $contact, array $faq): void
    {
        $manager->persist((new Home())
            ->setLanguage($language)
            ->setContent($home));

        $manager->persist((new About())
            ->setLanguage($language)
            ->setContent($about));

        $manager->persist((new Contact())
            ->setLanguage($language)
            ->setContent($contact));

        $manager->persist((new Faq())
            ->setLanguage($language)
            ->setContent($faq));
    }

    /**
     * @return array<string, mixed>
     */
    private function getFrenchHome(): array
    {
        return [
            'pages' => $this->getPageModules('fr'),
            'featuresTitle' => 'Fonctionnalités principales',
            'metricsTitle' => 'Indicateurs de performance',
            'stepsTitle' => 'Comment ça marche',
            'stepLabelPrefix' => 'Étape',
            'hero' => [
                'badge' => 'Accueil',
                'title' => 'Pilotez votre activité depuis un espace unique',
                'subtitle' => 'Cette page racine est alimentée avec un JSON fake pour clarifier le contrat backend.',
                'primaryCta' => 'Créer un projet',
                'secondaryCta' => 'Voir les tutoriels',
                'benefits' => ['Suivi en temps réel', 'Permissions avancées', 'Reporting exportable'],
            ],
            'featureCards' => [
                [
                    'icon' => 'mdi-view-dashboard-outline',
                    'title' => 'Dashboard unifié',
                    'description' => 'Vue centralisée de vos KPIs, tâches et alertes prioritaires.',
                ],
                [
                    'icon' => 'mdi-account-group-outline',
                    'title' => 'Collaboration équipe',
                    'description' => 'Partage d’informations et historique d’actions sur chaque module.',
                ],
                [
                    'icon' => 'mdi-shield-check-outline',
                    'title' => 'Sécurité renforcée',
                    'description' => 'Gestion des rôles et journalisation des accès sensibles.',
                ],
            ],
            'metrics' => [
                [
                    'value' => '250+',
                    'label' => 'Utilisateurs actifs / semaine',
                ],
                [
                    'value' => '99.9%',
                    'label' => 'Disponibilité service',
                ],
                [
                    'value' => '4.8/5',
                    'label' => 'Note moyenne client',
                ],
            ],
            'steps' => [
                [
                    'icon' => 'mdi-account-plus-outline',
                    'title' => 'Créer votre espace',
                    'description' => 'Initialisez votre organisation et invitez vos collaborateurs.',
                ],
                [
                    'icon' => 'mdi-tune-variant',
                    'title' => 'Configurer vos modules',
                    'description' => 'Activez les options nécessaires selon votre workflow.',
                ],
                [
                    'icon' => 'mdi-chart-areaspline',
                    'title' => 'Suivre et optimiser',
                    'description' => 'Analysez les résultats et ajustez vos actions en continu.',
                ],
            ],
            'cta' => [
                'title' => 'Prêt à aller plus loin ?',
                'description' => 'Ce bloc final doit aussi être renvoyé par le backend dans la réponse.',
                'primaryAction' => 'Demander une démo',
                'secondaryAction' => 'Contacter un expert',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getEnglishHome(): array
    {
        return [
            'pages' => $this->getPageModules('en'),
            'featuresTitle' => 'Key features',
            'metricsTitle' => 'Performance metrics',
            'stepsTitle' => 'How it works',
            'stepLabelPrefix' => 'Step',
            'hero' => [
                'badge' => 'Home',
                'title' => 'Manage your business from one unified space',
                'subtitle' => 'This landing page is powered by mock JSON to clarify the backend contract.',
                'primaryCta' => 'Create a project',
                'secondaryCta' => 'View tutorials',
                'benefits' => ['Real-time tracking', 'Advanced permissions', 'Exportable reporting'],
            ],
            'featureCards' => [
                [
                    'icon' => 'mdi-view-dashboard-outline',
                    'title' => 'Unified dashboard',
                    'description' => 'Centralized view of your KPIs, tasks, and high-priority alerts.',
                ],
                [
                    'icon' => 'mdi-account-group-outline',
                    'title' => 'Team collaboration',
                    'description' => 'Share information and action history across every module.',
                ],
                [
                    'icon' => 'mdi-shield-check-outline',
                    'title' => 'Enhanced security',
                    'description' => 'Role management and audit logs for sensitive access.',
                ],
            ],
            'metrics' => [
                [
                    'value' => '250+',
                    'label' => 'Active users / week',
                ],
                [
                    'value' => '99.9%',
                    'label' => 'Service availability',
                ],
                [
                    'value' => '4.8/5',
                    'label' => 'Average customer rating',
                ],
            ],
            'steps' => [
                [
                    'icon' => 'mdi-account-plus-outline',
                    'title' => 'Create your workspace',
                    'description' => 'Initialize your organization and invite your collaborators.',
                ],
                [
                    'icon' => 'mdi-tune-variant',
                    'title' => 'Configure your modules',
                    'description' => 'Enable the options you need for your workflow.',
                ],
                [
                    'icon' => 'mdi-chart-areaspline',
                    'title' => 'Monitor and optimize',
                    'description' => 'Analyze results and continuously adjust your actions.',
                ],
            ],
            'cta' => [
                'title' => 'Ready to go further?',
                'description' => 'This final block must also be returned by the backend response.',
                'primaryAction' => 'Request a demo',
                'secondaryAction' => 'Contact an expert',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getFrenchAbout(): array
    {
        return [
            'pages' => $this->getPageModules('fr'),
            'hero' => [
                'badge' => 'À propos',
                'title' => 'Nous aidons les équipes à lancer plus vite',
                'subtitle' => 'Page pilotée par un JSON mocké pour préparer le contrat backend.',
                'paragraphs' => [
                    'Cette section représente le bloc hero que le backend devra retourner.',
                    'Chaque champ visible ici est un exemple de donnée dynamique venant d’un endpoint.',
                ],
                'bullets' => [
                    'Positionnement produit',
                    'Proposition de valeur',
                    'Actions principales',
                ],
                'primaryCta' => 'Demander une démo',
                'secondaryCta' => 'Voir la roadmap',
            ],
            'metricsTitle' => 'Chiffres clés',
            'missionCards' => [
                [
                    'title' => 'Mission',
                    'description' => 'Ce que nous voulons accomplir à long terme.',
                    'paragraphs' => ['Rendre la collaboration produit plus simple.', 'Réduire le temps de mise en production.'],
                    'bullets' => ['Qualité', 'Vitesse', 'Transparence'],
                    'icon' => 'mdi-rocket-launch-outline',
                ],
                [
                    'title' => 'Valeurs',
                    'description' => 'Principes de fonctionnement pour l’équipe et les clients.',
                    'paragraphs' => ['Décisions basées sur la donnée.', 'Feedback continu des utilisateurs.'],
                    'bullets' => ['Ownership', 'Empathie', 'Amélioration continue'],
                    'icon' => 'mdi-hand-heart-outline',
                ],
            ],
            'metrics' => [
                [
                    'value' => '120+',
                    'label' => 'Projets livrés',
                    'context' => 'sur 24 derniers mois',
                    'icon' => 'mdi-briefcase-outline',
                ],
                [
                    'value' => '98%',
                    'label' => 'Satisfaction client',
                    'context' => 'NPS trimestriel',
                    'icon' => 'mdi-thumb-up-outline',
                ],
                [
                    'value' => '35%',
                    'label' => 'Gain de productivité',
                    'context' => 'moyenne observée',
                    'icon' => 'mdi-chart-line',
                ],
            ],
            'timelineTitle' => 'Timeline',
            'timeline' => [
                [
                    'title' => 'Lancement de la plateforme',
                    'period' => '2022',
                    'description' => 'Première version publique avec les fonctionnalités core.',
                    'highlights' => ['Gestion des utilisateurs', 'Tableau de bord', 'Auth sécurisée'],
                    'icon' => 'mdi-flag-outline',
                ],
                [
                    'title' => 'Ouverture API',
                    'period' => '2024',
                    'description' => 'Mise à disposition d’API publiques pour les intégrations.',
                    'highlights' => ['Endpoints documentés', 'Clés API', 'Webhooks'],
                    'icon' => 'mdi-api',
                ],
            ],
            'cta' => [
                'title' => 'Construisons la suite ensemble',
                'description' => 'Ce bloc prépare les informations de fin de page récupérées côté backend.',
                'primaryAction' => 'Parler à un expert',
                'secondaryAction' => 'Télécharger la brochure',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getEnglishAbout(): array
    {
        return [
            'pages' => $this->getPageModules('en'),
            'hero' => [
                'badge' => 'About',
                'title' => 'We help teams launch faster',
                'subtitle' => 'Page driven by mocked JSON to prepare the backend contract.',
                'paragraphs' => [
                    'This section represents the hero block that the backend will return.',
                    'Every visible field here is an example of dynamic data coming from an endpoint.',
                ],
                'bullets' => [
                    'Product positioning',
                    'Value proposition',
                    'Main actions',
                ],
                'primaryCta' => 'Request a demo',
                'secondaryCta' => 'View the roadmap',
            ],
            'metricsTitle' => 'Key figures',
            'missionCards' => [
                [
                    'title' => 'Mission',
                    'description' => 'What we aim to achieve in the long term.',
                    'paragraphs' => ['Make product collaboration simpler.', 'Reduce time to production.'],
                    'bullets' => ['Quality', 'Speed', 'Transparency'],
                    'icon' => 'mdi-rocket-launch-outline',
                ],
                [
                    'title' => 'Values',
                    'description' => 'Working principles for both the team and customers.',
                    'paragraphs' => ['Data-driven decisions.', 'Continuous feedback from users.'],
                    'bullets' => ['Ownership', 'Empathy', 'Continuous improvement'],
                    'icon' => 'mdi-hand-heart-outline',
                ],
            ],
            'metrics' => [
                [
                    'value' => '120+',
                    'label' => 'Delivered projects',
                    'context' => 'over the last 24 months',
                    'icon' => 'mdi-briefcase-outline',
                ],
                [
                    'value' => '98%',
                    'label' => 'Customer satisfaction',
                    'context' => 'quarterly NPS',
                    'icon' => 'mdi-thumb-up-outline',
                ],
                [
                    'value' => '35%',
                    'label' => 'Productivity gain',
                    'context' => 'observed average',
                    'icon' => 'mdi-chart-line',
                ],
            ],
            'timelineTitle' => 'Timeline',
            'timeline' => [
                [
                    'title' => 'Platform launch',
                    'period' => '2022',
                    'description' => 'First public version with core features.',
                    'highlights' => ['User management', 'Dashboard', 'Secure authentication'],
                    'icon' => 'mdi-flag-outline',
                ],
                [
                    'title' => 'API opening',
                    'period' => '2024',
                    'description' => 'Public APIs made available for integrations.',
                    'highlights' => ['Documented endpoints', 'API keys', 'Webhooks'],
                    'icon' => 'mdi-api',
                ],
            ],
            'cta' => [
                'title' => 'Let’s build what comes next together',
                'description' => 'This block prepares end-of-page information fetched from the backend.',
                'primaryAction' => 'Talk to an expert',
                'secondaryAction' => 'Download the brochure',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getFrenchContact(): array
    {
        return [
            'pages' => $this->getPageModules('fr'),
            'title' => 'Contact',
            'hero' => [
                'badge' => 'Contact',
                'title' => 'Parlons de votre besoin',
                'subtitle' => 'Page connectée à un JSON fake pour définir le contrat de données backend.',
                'primaryCta' => 'Planifier un appel',
                'secondaryCta' => 'Ouvrir un ticket',
            ],
            'channels' => [
                [
                    'label' => 'Email',
                    'value' => 'support@bro-world.io',
                    'details' => 'Réponse sous 24h',
                    'icon' => 'mdi-email-outline',
                ],
                [
                    'label' => 'Téléphone',
                    'value' => '+33 1 23 45 67 89',
                    'details' => 'Lundi au vendredi',
                    'icon' => 'mdi-phone-outline',
                ],
            ],
            'availability' => [
                'title' => 'Disponibilité',
                'description' => 'Créneaux de réponse gérés par l’équipe support.',
                'windows' => [
                    [
                        'label' => 'Support standard',
                        'value' => '09:00 - 18:00 CET',
                    ],
                    [
                        'label' => 'Urgences',
                        'value' => '24/7 pour incidents critiques',
                    ],
                ],
                'escalationTitle' => 'Escalade',
                'escalationBullets' => ['Niveau 1: support', 'Niveau 2: équipe produit', 'Niveau 3: engineering lead'],
            ],
            'form' => [
                'title' => 'Formulaire de contact',
                'description' => 'Les champs suivants représentent la structure attendue depuis l’API.',
                'fields' => [
                    'firstName' => 'Prénom',
                    'lastName' => 'Nom',
                    'email' => 'Email professionnel',
                    'topic' => 'Sujet',
                    'message' => 'Message',
                    'messagePlaceholder' => 'Décrivez votre demande…',
                ],
                'topics' => [
                    [
                        'value' => 'sales',
                        'label' => 'Demande commerciale',
                    ],
                    [
                        'value' => 'support',
                        'label' => 'Support technique',
                    ],
                    [
                        'value' => 'partnership',
                        'label' => 'Partenariat',
                    ],
                ],
                'privacyNote' => 'En soumettant ce formulaire, vous acceptez le traitement de vos données.',
                'submit' => 'Envoyer',
                'reset' => 'Réinitialiser',
            ],
            'cta' => [
                'title' => 'Autres canaux',
                'description' => 'Ces actions peuvent aussi être gérées dynamiquement côté backend.',
                'actions' => [
                    [
                        'label' => 'Chat en direct',
                        'variant' => 'primary',
                    ],
                    [
                        'label' => 'Centre d’aide',
                        'variant' => 'outlined',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getEnglishContact(): array
    {
        return [
            'pages' => $this->getPageModules('en'),
            'title' => 'Contact',
            'hero' => [
                'badge' => 'Contact',
                'title' => 'Let’s talk about your needs',
                'subtitle' => 'Page connected to mock JSON to define the backend data contract.',
                'primaryCta' => 'Schedule a call',
                'secondaryCta' => 'Open a ticket',
            ],
            'channels' => [
                [
                    'label' => 'Email',
                    'value' => 'support@bro-world.io',
                    'details' => 'Reply within 24h',
                    'icon' => 'mdi-email-outline',
                ],
                [
                    'label' => 'Phone',
                    'value' => '+33 1 23 45 67 89',
                    'details' => 'Monday to Friday',
                    'icon' => 'mdi-phone-outline',
                ],
            ],
            'availability' => [
                'title' => 'Availability',
                'description' => 'Response windows managed by the support team.',
                'windows' => [
                    [
                        'label' => 'Standard support',
                        'value' => '09:00 - 18:00 CET',
                    ],
                    [
                        'label' => 'Emergency',
                        'value' => '24/7 for critical incidents',
                    ],
                ],
                'escalationTitle' => 'Escalation',
                'escalationBullets' => ['Level 1: support', 'Level 2: product team', 'Level 3: engineering lead'],
            ],
            'form' => [
                'title' => 'Contact form',
                'description' => 'The following fields represent the structure expected from the API.',
                'fields' => [
                    'firstName' => 'First name',
                    'lastName' => 'Last name',
                    'email' => 'Business email',
                    'topic' => 'Topic',
                    'message' => 'Message',
                    'messagePlaceholder' => 'Describe your request…',
                ],
                'topics' => [
                    [
                        'value' => 'sales',
                        'label' => 'Sales inquiry',
                    ],
                    [
                        'value' => 'support',
                        'label' => 'Technical support',
                    ],
                    [
                        'value' => 'partnership',
                        'label' => 'Partnership',
                    ],
                ],
                'privacyNote' => 'By submitting this form, you agree to the processing of your data.',
                'submit' => 'Send',
                'reset' => 'Reset',
            ],
            'cta' => [
                'title' => 'Other channels',
                'description' => 'These actions can also be managed dynamically by the backend.',
                'actions' => [
                    [
                        'label' => 'Live chat',
                        'variant' => 'primary',
                    ],
                    [
                        'label' => 'Help center',
                        'variant' => 'outlined',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getFrenchFaq(): array
    {
        return [
            'pages' => $this->getPageModules('fr'),
            'hero' => [
                'badge' => 'FAQ',
                'title' => 'Questions fréquentes',
                'subtitle' => 'Toutes les informations de cette page sont simulées via JSON local.',
                'primaryCta' => 'Contacter le support',
                'secondaryCta' => 'Voir la documentation',
            ],
            'search' => [
                'label' => 'Rechercher une question',
                'placeholder' => 'Ex: facturation, sécurité, délais…',
            ],
            'categories' => [
                [
                    'key' => 'all',
                    'label' => 'Toutes',
                    'color' => 'primary',
                    'description' => 'Toutes les catégories',
                ],
                [
                    'key' => 'billing',
                    'label' => 'Facturation',
                    'color' => 'indigo',
                    'description' => 'Paiements, abonnements, factures',
                ],
                [
                    'key' => 'security',
                    'label' => 'Sécurité',
                    'color' => 'teal',
                    'description' => 'Protection des données et accès',
                ],
                [
                    'key' => 'product',
                    'label' => 'Produit',
                    'color' => 'deep-orange',
                    'description' => 'Fonctionnalités et roadmap',
                ],
            ],
            'items' => [
                [
                    'category' => 'billing',
                    'question' => 'Comment récupérer une facture ?',
                    'answer' => 'Les factures sont disponibles depuis votre espace admin.',
                    'detailsParagraphs' => ['Chaque facture est exportable en PDF.', 'Un email de confirmation est envoyé à chaque paiement.'],
                    'bullets' => ['Format PDF', 'Historique complet', 'Téléchargement immédiat'],
                ],
                [
                    'category' => 'security',
                    'question' => 'Comment fonctionne la gestion des accès ?',
                    'answer' => 'Vous pouvez créer des rôles avec permissions granulaires.',
                    'detailsParagraphs' => ['Le backend devra renvoyer rôles et permissions disponibles.'],
                    'bullets' => ['Rôles personnalisés', 'Audit logs', 'MFA en option'],
                ],
            ],
            'emptyState' => [
                'title' => 'Aucun résultat',
                'description' => 'Aucune FAQ ne correspond à votre recherche.',
                'suggestion' => 'Essayez un autre mot-clé ou changez de catégorie.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getEnglishFaq(): array
    {
        return [
            'pages' => $this->getPageModules('en'),
            'hero' => [
                'badge' => 'FAQ',
                'title' => 'Frequently asked questions',
                'subtitle' => 'All information on this page is simulated through local JSON.',
                'primaryCta' => 'Contact support',
                'secondaryCta' => 'View documentation',
            ],
            'search' => [
                'label' => 'Search a question',
                'placeholder' => 'Ex: billing, security, timelines…',
            ],
            'categories' => [
                [
                    'key' => 'all',
                    'label' => 'All',
                    'color' => 'primary',
                    'description' => 'All categories',
                ],
                [
                    'key' => 'billing',
                    'label' => 'Billing',
                    'color' => 'indigo',
                    'description' => 'Payments, subscriptions, invoices',
                ],
                [
                    'key' => 'security',
                    'label' => 'Security',
                    'color' => 'teal',
                    'description' => 'Data protection and access',
                ],
                [
                    'key' => 'product',
                    'label' => 'Product',
                    'color' => 'deep-orange',
                    'description' => 'Features and roadmap',
                ],
            ],
            'items' => [
                [
                    'category' => 'billing',
                    'question' => 'How can I retrieve an invoice?',
                    'answer' => 'Invoices are available from your admin workspace.',
                    'detailsParagraphs' => ['Each invoice can be exported as PDF.', 'A confirmation email is sent for every payment.'],
                    'bullets' => ['PDF format', 'Full history', 'Instant download'],
                ],
                [
                    'category' => 'security',
                    'question' => 'How does access management work?',
                    'answer' => 'You can create roles with granular permissions.',
                    'detailsParagraphs' => ['The backend should return available roles and permissions.'],
                    'bullets' => ['Custom roles', 'Audit logs', 'Optional MFA'],
                ],
            ],
            'emptyState' => [
                'title' => 'No results',
                'description' => 'No FAQ matches your search.',
                'suggestion' => 'Try another keyword or switch category.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getSpanishHome(): array
    {
        return [
            ...$this->getEnglishHome(),
            'pages' => $this->getPageModules('es'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getSpanishAbout(): array
    {
        return [
            ...$this->getEnglishAbout(),
            'pages' => $this->getPageModules('es'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getSpanishContact(): array
    {
        return [
            ...$this->getEnglishContact(),
            'pages' => $this->getPageModules('es'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getSpanishFaq(): array
    {
        return [
            ...$this->getEnglishFaq(),
            'pages' => $this->getPageModules('es'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getGermanHome(): array
    {
        return [
            ...$this->getEnglishHome(),
            'pages' => $this->getPageModules('de'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getGermanAbout(): array
    {
        return [
            ...$this->getEnglishAbout(),
            'pages' => $this->getPageModules('de'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getGermanContact(): array
    {
        return [
            ...$this->getEnglishContact(),
            'pages' => $this->getPageModules('de'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getGermanFaq(): array
    {
        return [
            ...$this->getEnglishFaq(),
            'pages' => $this->getPageModules('de'),
        ];
    }

    /**
     * @return list<array{slug: string, title: string, description: string}>
     */
    private function getPageModules(string $languageCode): array
    {
        return match ($languageCode) {
            'fr' => [
                ['slug' => 'crm', 'title' => 'CRM', 'description' => 'Gestion commerciale et relation client.'],
                ['slug' => 'learning', 'title' => 'Learning', 'description' => 'Parcours d’apprentissage et progression.'],
                ['slug' => 'job', 'title' => 'Job', 'description' => 'Offres, candidatures et suivi des recrutements.'],
                ['slug' => 'school', 'title' => 'School', 'description' => 'Gestion scolaire, classes et progression des élèves.'],
                ['slug' => 'shop', 'title' => 'Shop', 'description' => 'Catalogue produits et commandes e-commerce.'],
                ['slug' => 'jobs-applications', 'title' => 'Jobs Applications', 'description' => 'Suivi des candidatures et statuts de recrutement.'],
                ['slug' => 'jobs-offers', 'title' => 'Jobs Offers', 'description' => 'Gestion et publication des offres d’emploi.'],
                ['slug' => 'learning-courses', 'title' => 'Learning Courses', 'description' => 'Catalogue des cours et parcours pédagogiques.'],
                ['slug' => 'learning-teachers', 'title' => 'Learning Teachers', 'description' => 'Annuaire des enseignants et disponibilité.'],
            ],
            'es' => [
                ['slug' => 'crm', 'title' => 'CRM', 'description' => 'Gestión comercial y relación con clientes.'],
                ['slug' => 'learning', 'title' => 'Learning', 'description' => 'Rutas de aprendizaje y progreso.'],
                ['slug' => 'job', 'title' => 'Job', 'description' => 'Ofertas, candidaturas y seguimiento de contratación.'],
                ['slug' => 'school', 'title' => 'School', 'description' => 'Gestión escolar, clases y progreso de estudiantes.'],
                ['slug' => 'shop', 'title' => 'Shop', 'description' => 'Catálogo de productos y pedidos e-commerce.'],
                ['slug' => 'jobs-applications', 'title' => 'Jobs Applications', 'description' => 'Seguimiento de candidaturas y estado de contratación.'],
                ['slug' => 'jobs-offers', 'title' => 'Jobs Offers', 'description' => 'Gestión y publicación de ofertas de empleo.'],
                ['slug' => 'learning-courses', 'title' => 'Learning Courses', 'description' => 'Catálogo de cursos y rutas de aprendizaje.'],
                ['slug' => 'learning-teachers', 'title' => 'Learning Teachers', 'description' => 'Directorio de profesores y disponibilidad.'],
            ],
            'de' => [
                ['slug' => 'crm', 'title' => 'CRM', 'description' => 'Vertrieb und Kundenbeziehungen steuern.'],
                ['slug' => 'learning', 'title' => 'Learning', 'description' => 'Lernpfade und Fortschritt verwalten.'],
                ['slug' => 'job', 'title' => 'Job', 'description' => 'Stellen, Bewerbungen und Recruiting-Tracking.'],
                ['slug' => 'school', 'title' => 'School', 'description' => 'Schulverwaltung, Klassen und Lernfortschritt.'],
                ['slug' => 'shop', 'title' => 'Shop', 'description' => 'Produktkatalog und E-Commerce-Bestellungen.'],
                ['slug' => 'jobs-applications', 'title' => 'Jobs Applications', 'description' => 'Bewerbungen und Recruiting-Status verfolgen.'],
                ['slug' => 'jobs-offers', 'title' => 'Jobs Offers', 'description' => 'Stellenangebote verwalten und veröffentlichen.'],
                ['slug' => 'learning-courses', 'title' => 'Learning Courses', 'description' => 'Kurskatalog und Lernpfade verwalten.'],
                ['slug' => 'learning-teachers', 'title' => 'Learning Teachers', 'description' => 'Lehrkräfte-Verzeichnis und Verfügbarkeit.'],
            ],
            default => [
                ['slug' => 'crm', 'title' => 'CRM', 'description' => 'Sales workflow and customer relationship management.'],
                ['slug' => 'learning', 'title' => 'Learning', 'description' => 'Learning paths and progress tracking.'],
                ['slug' => 'job', 'title' => 'Job', 'description' => 'Open positions, applications and hiring workflow.'],
                ['slug' => 'school', 'title' => 'School', 'description' => 'School operations, classes, and learner progress.'],
                ['slug' => 'shop', 'title' => 'Shop', 'description' => 'Product catalog and e-commerce orders.'],
                ['slug' => 'jobs-applications', 'title' => 'Jobs Applications', 'description' => 'Track applications and hiring statuses.'],
                ['slug' => 'jobs-offers', 'title' => 'Jobs Offers', 'description' => 'Manage and publish job offers.'],
                ['slug' => 'learning-courses', 'title' => 'Learning Courses', 'description' => 'Course catalog and learning paths.'],
                ['slug' => 'learning-teachers', 'title' => 'Learning Teachers', 'description' => 'Teacher directory and availability.'],
            ],
        };
    }
}
