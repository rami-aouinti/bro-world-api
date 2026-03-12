<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260312100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add assignees for CRM tasks and task requests';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE crm_task_assignee (task_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", user_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", INDEX IDX_1579FBF98DB60186 (task_id), INDEX IDX_1579FBF9A76ED395 (user_id), PRIMARY KEY(task_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE crm_task_request_assignee (task_request_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", user_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", INDEX IDX_14A92EAA6322B482 (task_request_id), INDEX IDX_14A92EAAA76ED395 (user_id), PRIMARY KEY(task_request_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE crm_task_assignee ADD CONSTRAINT FK_1579FBF98DB60186 FOREIGN KEY (task_id) REFERENCES crm_task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE crm_task_assignee ADD CONSTRAINT FK_1579FBF9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE crm_task_request_assignee ADD CONSTRAINT FK_14A92EAA6322B482 FOREIGN KEY (task_request_id) REFERENCES crm_task_request (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE crm_task_request_assignee ADD CONSTRAINT FK_14A92EAAA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE crm_task_assignee DROP FOREIGN KEY FK_1579FBF98DB60186');
        $this->addSql('ALTER TABLE crm_task_assignee DROP FOREIGN KEY FK_1579FBF9A76ED395');
        $this->addSql('ALTER TABLE crm_task_request_assignee DROP FOREIGN KEY FK_14A92EAA6322B482');
        $this->addSql('ALTER TABLE crm_task_request_assignee DROP FOREIGN KEY FK_14A92EAAA76ED395');
        $this->addSql('DROP TABLE crm_task_assignee');
        $this->addSql('DROP TABLE crm_task_request_assignee');
    }
}
