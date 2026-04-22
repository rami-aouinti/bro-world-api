<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CRM GitHub sync: store repository metrics and visibility fields.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE crm_repository ADD visibility VARCHAR(20) DEFAULT NULL, ADD primary_language VARCHAR(120) DEFAULT NULL, ADD stars_count INT DEFAULT 0 NOT NULL, ADD forks_count INT DEFAULT 0 NOT NULL, ADD watchers_count INT DEFAULT 0 NOT NULL, ADD open_issues_count INT DEFAULT 0 NOT NULL, ADD is_archived TINYINT(1) DEFAULT 0 NOT NULL, ADD is_disabled TINYINT(1) DEFAULT 0 NOT NULL, ADD last_pushed_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE crm_repository DROP visibility, DROP primary_language, DROP stars_count, DROP forks_count, DROP watchers_count, DROP open_issues_count, DROP is_archived, DROP is_disabled, DROP last_pushed_at');
    }
}
