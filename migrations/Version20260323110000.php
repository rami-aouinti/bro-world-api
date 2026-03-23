<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260323110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create GitHub issue mapping table for CRM task requests.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE crm_task_request_github_issue (task_request_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', provider VARCHAR(30) NOT NULL DEFAULT 'github', repository_full_name VARCHAR(255) NOT NULL, issue_number INT DEFAULT NULL, issue_node_id VARCHAR(255) DEFAULT NULL, issue_url VARCHAR(1024) DEFAULT NULL, sync_status VARCHAR(40) NOT NULL DEFAULT 'pending', last_synced_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', PRIMARY KEY(task_request_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE crm_task_request_github_issue ADD CONSTRAINT FK_CRM_TASK_REQUEST_GITHUB_ISSUE_TASK_REQUEST FOREIGN KEY (task_request_id) REFERENCES crm_task_request (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_crm_task_request_github_issue_sync_status ON crm_task_request_github_issue (sync_status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE crm_task_request_github_issue DROP FOREIGN KEY FK_CRM_TASK_REQUEST_GITHUB_ISSUE_TASK_REQUEST');
        $this->addSql('DROP TABLE crm_task_request_github_issue');
    }
}
