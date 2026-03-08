<?php

declare(strict_types=1);

// phpcs:ignoreFile
namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260309150000 extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Create notification table linked to user sender and recipient.';
    }

    #[Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('CREATE TABLE notification (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid_binary_ordered_time)\', from_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid_binary_ordered_time)\', recipient_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid_binary_ordered_time)\', title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, type VARCHAR(100) NOT NULL, created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_notification_from_id (from_id), INDEX idx_notification_recipient_id (recipient_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT fk_notification_from_id FOREIGN KEY (from_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT fk_notification_recipient_id FOREIGN KEY (recipient_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY fk_notification_from_id');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY fk_notification_recipient_id');
        $this->addSql('DROP TABLE notification');
    }
}
