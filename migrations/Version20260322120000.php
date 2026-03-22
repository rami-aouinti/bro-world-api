<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260322120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add table to persist CRM GitHub webhook events for idempotence and async processing.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE crm_github_webhook_event (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', delivery_id VARCHAR(255) NOT NULL, event_name VARCHAR(80) NOT NULL, event_action VARCHAR(80) DEFAULT NULL, repository_full_name VARCHAR(255) DEFAULT NULL, signature VARCHAR(255) DEFAULT NULL, checksum VARCHAR(64) NOT NULL, status VARCHAR(40) NOT NULL, processed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', payload JSON NOT NULL, created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uq_crm_github_webhook_event_delivery_id (delivery_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE crm_github_webhook_event');
    }
}
