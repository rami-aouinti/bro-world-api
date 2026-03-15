<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional blog relation to CRM task and task request with unique one-to-one constraints.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE crm_task ADD blog_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)'");
        $this->addSql("ALTER TABLE crm_task_request ADD blog_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)'");

        $this->addSql('ALTER TABLE crm_task ADD CONSTRAINT UNIQ_CRM_TASK_BLOG UNIQUE (blog_id)');
        $this->addSql('ALTER TABLE crm_task_request ADD CONSTRAINT UNIQ_CRM_TASK_REQUEST_BLOG UNIQUE (blog_id)');

        $this->addSql('ALTER TABLE crm_task ADD CONSTRAINT FK_CRM_TASK_BLOG FOREIGN KEY (blog_id) REFERENCES blog (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE crm_task_request ADD CONSTRAINT FK_CRM_TASK_REQUEST_BLOG FOREIGN KEY (blog_id) REFERENCES blog (id) ON DELETE SET NULL');

        $this->addSql('UPDATE crm_task_request tr INNER JOIN crm_task t ON tr.task_id = t.id SET tr.blog_id = t.blog_id WHERE tr.blog_id IS NULL AND t.blog_id IS NOT NULL');
        $this->addSql('UPDATE crm_task t INNER JOIN crm_task_request tr ON tr.task_id = t.id SET t.blog_id = tr.blog_id WHERE t.blog_id IS NULL AND tr.blog_id IS NOT NULL');

        $taskNullCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM crm_task WHERE blog_id IS NULL');
        if (0 === $taskNullCount) {
            $this->addSql("ALTER TABLE crm_task CHANGE blog_id blog_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)'");
        }

        $taskRequestNullCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM crm_task_request WHERE blog_id IS NULL');
        if (0 === $taskRequestNullCount) {
            $this->addSql("ALTER TABLE crm_task_request CHANGE blog_id blog_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)'");
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE crm_task DROP FOREIGN KEY FK_CRM_TASK_BLOG');
        $this->addSql('ALTER TABLE crm_task_request DROP FOREIGN KEY FK_CRM_TASK_REQUEST_BLOG');

        $this->addSql('ALTER TABLE crm_task DROP INDEX UNIQ_CRM_TASK_BLOG');
        $this->addSql('ALTER TABLE crm_task_request DROP INDEX UNIQ_CRM_TASK_REQUEST_BLOG');

        $this->addSql('ALTER TABLE crm_task DROP blog_id');
        $this->addSql('ALTER TABLE crm_task_request DROP blog_id');
    }
}
