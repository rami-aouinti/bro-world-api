<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CRM: add optional blog relation for projects and sprints.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE crm_project ADD blog_id BINARY(16) DEFAULT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)"');
        $this->addSql('ALTER TABLE crm_sprint ADD blog_id BINARY(16) DEFAULT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)"');

        $this->addSql('ALTER TABLE crm_project ADD CONSTRAINT FK_CE64D834DAA2FB99 FOREIGN KEY (blog_id) REFERENCES blog (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE crm_sprint ADD CONSTRAINT FK_74A6B74EDAA2FB99 FOREIGN KEY (blog_id) REFERENCES blog (id) ON DELETE SET NULL');

        $this->addSql('CREATE INDEX IDX_CE64D834DAA2FB99 ON crm_project (blog_id)');
        $this->addSql('CREATE INDEX IDX_74A6B74EDAA2FB99 ON crm_sprint (blog_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE crm_project DROP FOREIGN KEY FK_CE64D834DAA2FB99');
        $this->addSql('ALTER TABLE crm_sprint DROP FOREIGN KEY FK_74A6B74EDAA2FB99');

        $this->addSql('DROP INDEX IDX_CE64D834DAA2FB99 ON crm_project');
        $this->addSql('DROP INDEX IDX_74A6B74EDAA2FB99 ON crm_sprint');

        $this->addSql('ALTER TABLE crm_project DROP blog_id');
        $this->addSql('ALTER TABLE crm_sprint DROP blog_id');
    }
}
