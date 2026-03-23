<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260323133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store GitHub issue mapping on CRM tasks and add unique/index constraints for CRM external mappings.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE crm_task ADD github_issue JSON DEFAULT NULL COMMENT '(DC2Type:json)'");
        $this->addSql('CREATE INDEX idx_crm_repository_project_provider_external ON crm_repository (project_id, provider, external_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_crm_task_request_gh_issue_repo_number ON crm_task_request_github_issue (provider, repository_full_name, issue_number)');
        $this->addSql('CREATE INDEX idx_crm_task_request_gh_issue_node ON crm_task_request_github_issue (issue_node_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_crm_task_request_gh_issue_node ON crm_task_request_github_issue');
        $this->addSql('DROP INDEX uq_crm_task_request_gh_issue_repo_number ON crm_task_request_github_issue');
        $this->addSql('DROP INDEX idx_crm_repository_project_provider_external ON crm_repository');
        $this->addSql('ALTER TABLE crm_task DROP github_issue');
    }
}
