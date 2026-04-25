<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260425110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add planned hours on task requests and introduce task request worklogs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE crm_task_request ADD planned_hours DOUBLE PRECISION NOT NULL DEFAULT 0');
        $this->addSql('CREATE TABLE crm_task_request_worklog (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", task_request_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", employee_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", logged_by_user_id BINARY(16) DEFAULT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", hours DOUBLE PRECISION NOT NULL, logged_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", INDEX idx_crm_task_request_worklog_task_request_id (task_request_id), INDEX idx_crm_task_request_worklog_employee_id (employee_id), INDEX idx_crm_task_request_worklog_logged_at (logged_at), INDEX IDX_E3A39295F8C3BF8E (logged_by_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE crm_task_request_worklog ADD CONSTRAINT FK_48A8BE753D7470A8 FOREIGN KEY (task_request_id) REFERENCES crm_task_request (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE crm_task_request_worklog ADD CONSTRAINT FK_48A8BE758C03F15C FOREIGN KEY (employee_id) REFERENCES crm_employee (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE crm_task_request_worklog ADD CONSTRAINT FK_E3A39295F8C3BF8E FOREIGN KEY (logged_by_user_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE crm_task_request_worklog DROP FOREIGN KEY FK_48A8BE753D7470A8');
        $this->addSql('ALTER TABLE crm_task_request_worklog DROP FOREIGN KEY FK_48A8BE758C03F15C');
        $this->addSql('ALTER TABLE crm_task_request_worklog DROP FOREIGN KEY FK_E3A39295F8C3BF8E');
        $this->addSql('DROP TABLE crm_task_request_worklog');
        $this->addSql('ALTER TABLE crm_task_request DROP planned_hours');
    }
}
