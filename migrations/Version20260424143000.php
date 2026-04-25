<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create notification_template table to store synchronized Mailjet templates and variables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notification_template (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", provider_template_id INT NOT NULL, name VARCHAR(255) NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, variables JSON NOT NULL, created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", UNIQUE INDEX uniq_notification_template_provider_id (provider_template_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification_template');
    }
}
