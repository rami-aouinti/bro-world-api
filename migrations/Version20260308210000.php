<?php

declare(strict_types=1);

// phpcs:ignoreFile
namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260308210000 extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Create calendar and calendar_event tables.';
    }

    #[Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('CREATE TABLE calendar (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid_binary_ordered_time)\', user_id BINARY(16) DEFAULT NULL, title VARCHAR(255) NOT NULL, created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_FC84F7D8A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE calendar_event (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid_binary_ordered_time)\', user_id BINARY(16) DEFAULT NULL, calendar_id BINARY(16) DEFAULT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, start_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', end_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', status VARCHAR(25) NOT NULL DEFAULT \'confirmed\', visibility VARCHAR(25) NOT NULL DEFAULT \'private\', created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_calendar_event_start_at (start_at), INDEX idx_calendar_event_end_at (end_at), INDEX idx_calendar_event_status (status), INDEX idx_calendar_event_visibility (visibility), INDEX idx_calendar_event_user_id (user_id), INDEX idx_calendar_event_calendar_id (calendar_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE calendar ADD CONSTRAINT FK_FC84F7D8A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE calendar_event ADD CONSTRAINT FK_E72A1268A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE calendar_event ADD CONSTRAINT FK_E72A1268A40BC2D5 FOREIGN KEY (calendar_id) REFERENCES calendar (id) ON DELETE SET NULL');
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('ALTER TABLE calendar_event DROP FOREIGN KEY FK_E72A1268A76ED395');
        $this->addSql('ALTER TABLE calendar_event DROP FOREIGN KEY FK_E72A1268A40BC2D5');
        $this->addSql('ALTER TABLE calendar DROP FOREIGN KEY FK_FC84F7D8A76ED395');
        $this->addSql('DROP TABLE calendar_event');
        $this->addSql('DROP TABLE calendar');
    }
}
