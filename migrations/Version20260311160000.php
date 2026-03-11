<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260311160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enhance CRM schema with enums and additional fields for company/project/sprint/task/task_request';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE crm_company ADD industry VARCHAR(120) DEFAULT NULL, ADD website VARCHAR(255) DEFAULT NULL, ADD contact_email VARCHAR(255) DEFAULT NULL, ADD phone VARCHAR(60) DEFAULT NULL');

        $this->addSql("ALTER TABLE crm_project ADD code VARCHAR(80) DEFAULT NULL, ADD description LONGTEXT DEFAULT NULL, ADD status VARCHAR(25) DEFAULT 'planned' NOT NULL, ADD started_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD due_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");

        $this->addSql("ALTER TABLE crm_sprint ADD goal LONGTEXT DEFAULT NULL, ADD start_date DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)', ADD end_date DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)', ADD status VARCHAR(25) DEFAULT 'planned' NOT NULL");

        $this->addSql("ALTER TABLE crm_task ADD description LONGTEXT DEFAULT NULL, ADD status VARCHAR(25) DEFAULT 'todo' NOT NULL, ADD priority VARCHAR(25) DEFAULT 'medium' NOT NULL, ADD due_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD estimated_hours DOUBLE PRECISION DEFAULT NULL");

        $this->addSql("ALTER TABLE crm_task_request ADD description LONGTEXT DEFAULT NULL, ADD requested_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD resolved_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql("UPDATE crm_task_request SET requested_at = created_at WHERE requested_at IS NULL");
        $this->addSql("ALTER TABLE crm_task_request CHANGE requested_at requested_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE crm_company DROP industry, DROP website, DROP contact_email, DROP phone');
        $this->addSql('ALTER TABLE crm_project DROP code, DROP description, DROP status, DROP started_at, DROP due_at');
        $this->addSql('ALTER TABLE crm_sprint DROP goal, DROP start_date, DROP end_date, DROP status');
        $this->addSql('ALTER TABLE crm_task DROP description, DROP status, DROP priority, DROP due_at, DROP estimated_hours');
        $this->addSql('ALTER TABLE crm_task_request DROP description, DROP requested_at, DROP resolved_at');
    }
}
