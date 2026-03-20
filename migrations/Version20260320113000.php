<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260320113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add color to quiz_category and populate category colors.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE quiz_category ADD color VARCHAR(7) DEFAULT '#64748B' NOT NULL AFTER position");

        $this->addSql("UPDATE quiz_category SET color = '#6366F1' WHERE slug = 'general'");
        $this->addSql("UPDATE quiz_category SET color = '#0EA5E9' WHERE slug = 'backend'");
        $this->addSql("UPDATE quiz_category SET color = '#EC4899' WHERE slug = 'frontend'");
        $this->addSql("UPDATE quiz_category SET color = '#14B8A6' WHERE slug = 'devops'");
        $this->addSql("UPDATE quiz_category SET color = '#8B5CF6' WHERE slug = 'onboarding'");
        $this->addSql("UPDATE quiz_category SET color = '#06B6D4' WHERE slug = 'data'");
        $this->addSql("UPDATE quiz_category SET color = '#EF4444' WHERE slug = 'security'");
        $this->addSql("UPDATE quiz_category SET color = '#F97316' WHERE slug = 'architecture'");
        $this->addSql("UPDATE quiz_category SET color = '#84CC16' WHERE slug = 'mobile'");
        $this->addSql("UPDATE quiz_category SET color = '#64748B' WHERE slug = 'testing'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quiz_category DROP color');
    }
}
