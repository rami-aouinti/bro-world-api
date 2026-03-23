<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260323150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create CRM GitHub sync job table for bootstrap tracking and resume.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE crm_github_sync_job (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', application_slug VARCHAR(120) NOT NULL, owner VARCHAR(255) NOT NULL, started_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', finished_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', status VARCHAR(40) NOT NULL, projects_created INT NOT NULL, repos_attached INT NOT NULL, issues_imported INT NOT NULL, errors_count INT NOT NULL, errors JSON NOT NULL, parameters JSON NOT NULL, created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX idx_crm_gh_sync_job_app_status (application_slug, status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE crm_github_sync_job');
    }
}
