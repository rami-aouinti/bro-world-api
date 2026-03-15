<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link CRM employee to optional user account.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE crm_employee ADD user_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)'");
        $this->addSql('ALTER TABLE crm_employee ADD CONSTRAINT FK_8B2F4355A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_8B2F4355A76ED395 ON crm_employee (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE crm_employee DROP FOREIGN KEY FK_8B2F4355A76ED395');
        $this->addSql('DROP INDEX IDX_8B2F4355A76ED395 ON crm_employee');
        $this->addSql('ALTER TABLE crm_employee DROP user_id');
    }
}
