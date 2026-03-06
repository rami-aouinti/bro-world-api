<?php

declare(strict_types=1);

// phpcs:ignoreFile
namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

/**
 * Add platform_key and plugin_key columns.
 */
final class Version20260306190000 extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Add platform_key to platform and plugin_key to plugin with default enum values.';
    }

    #[Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql("ALTER TABLE platform ADD platform_key VARCHAR(25) NOT NULL DEFAULT 'crm'");
        $this->addSql("ALTER TABLE plugin ADD plugin_key VARCHAR(25) NOT NULL DEFAULT 'chat'");
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('ALTER TABLE platform DROP platform_key');
        $this->addSql('ALTER TABLE plugin DROP plugin_key');
    }
}
