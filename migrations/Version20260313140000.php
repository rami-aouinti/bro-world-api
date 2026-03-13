<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260313140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make CRM core parent relations mandatory and clean orphan rows before enforcing NOT NULL constraints.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM crm_task_request WHERE task_id IS NULL');
        $this->addSql('DELETE FROM crm_task WHERE project_id IS NULL');
        $this->addSql('DELETE FROM crm_sprint WHERE project_id IS NULL');
        $this->addSql('DELETE FROM crm_project WHERE company_id IS NULL');
        $this->addSql('DELETE FROM crm_company WHERE crm_id IS NULL');

        $this->addSql("ALTER TABLE crm_company CHANGE crm_id crm_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)'");
        $this->addSql("ALTER TABLE crm_project CHANGE company_id company_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)'");
        $this->addSql("ALTER TABLE crm_sprint CHANGE project_id project_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)'");
        $this->addSql("ALTER TABLE crm_task CHANGE project_id project_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)'");
        $this->addSql("ALTER TABLE crm_task_request CHANGE task_id task_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE crm_task_request CHANGE task_id task_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)'");
        $this->addSql("ALTER TABLE crm_task CHANGE project_id project_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)'");
        $this->addSql("ALTER TABLE crm_sprint CHANGE project_id project_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)'");
        $this->addSql("ALTER TABLE crm_project CHANGE company_id company_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)'");
        $this->addSql("ALTER TABLE crm_company CHANGE crm_id crm_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)'");
    }
}
