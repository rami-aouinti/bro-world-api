<?php

declare(strict_types=1);

// phpcs:ignoreFile
namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

/**
 * Add slug column to platform_application.
 */
final class Version20260306180000 extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Add slug column to platform_application and backfill existing records.';
    }

    #[Override]
    public function isTransactional(): bool
    {
        return false;
    }

    #[Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql("ALTER TABLE platform_application ADD slug VARCHAR(255) NOT NULL DEFAULT ''");
        $this->addSql("UPDATE platform_application SET slug = CONCAT('app-', LOWER(HEX(id))) WHERE slug = ''");
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('ALTER TABLE platform_application DROP slug');
    }
}
