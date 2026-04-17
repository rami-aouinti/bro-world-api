<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add self-referencing parent_task_id relation on crm_task for subtasks.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE crm_task ADD parent_task_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid_binary_ordered_time)\'');
        $this->addSql('ALTER TABLE crm_task ADD CONSTRAINT FK_FA9A9B9D801B5A19 FOREIGN KEY (parent_task_id) REFERENCES crm_task (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_FA9A9B9D801B5A19 ON crm_task (parent_task_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE crm_task DROP FOREIGN KEY FK_FA9A9B9D801B5A19');
        $this->addSql('DROP INDEX IDX_FA9A9B9D801B5A19 ON crm_task');
        $this->addSql('ALTER TABLE crm_task DROP parent_task_id');
    }
}

