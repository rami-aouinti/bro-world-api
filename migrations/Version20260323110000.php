<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260323110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create CRM task request GitHub branch mapping table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE crm_task_request_github_branch (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', task_request_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', repository_full_name VARCHAR(255) NOT NULL, branch_name VARCHAR(255) NOT NULL, branch_sha VARCHAR(255) DEFAULT NULL, branch_url VARCHAR(1024) DEFAULT NULL, issue_number INT DEFAULT NULL, sync_status VARCHAR(40) DEFAULT 'pending' NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', last_synced_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', metadata JSON NOT NULL, INDEX IDX_CRM_TASK_REQUEST_GH_BRANCH_TASK_REQUEST (task_request_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE crm_task_request_github_branch ADD CONSTRAINT FK_CRM_TASK_REQUEST_GH_BRANCH_TASK_REQUEST FOREIGN KEY (task_request_id) REFERENCES crm_task_request (id) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX uq_crm_task_request_gh_branch ON crm_task_request_github_branch (task_request_id, repository_full_name, branch_name)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE crm_task_request_github_branch');
    }
}
