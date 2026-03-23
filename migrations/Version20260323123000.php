<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260323123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add metadata payload to crm_task_request_github_issue for webhook loop protection.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE crm_task_request_github_issue ADD metadata JSON NOT NULL COMMENT '(DC2Type:json)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE crm_task_request_github_issue DROP metadata');
    }
}
