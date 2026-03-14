<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260314150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add CRM attachments and project wiki pages.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE crm_project ADD attachments JSON NOT NULL DEFAULT ('[]'), ADD wiki_pages JSON NOT NULL DEFAULT ('[]')");
        $this->addSql("ALTER TABLE crm_task ADD attachments JSON NOT NULL DEFAULT ('[]')");
        $this->addSql("ALTER TABLE crm_task_request ADD attachments JSON NOT NULL DEFAULT ('[]')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE crm_project DROP attachments, DROP wiki_pages');
        $this->addSql('ALTER TABLE crm_task DROP attachments');
        $this->addSql('ALTER TABLE crm_task_request DROP attachments');
    }
}
