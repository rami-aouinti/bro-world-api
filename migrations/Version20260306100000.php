<?php

declare(strict_types=1);

// phpcs:ignoreFile
namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

/**
 * Create plugin table.
 */
final class Version20260306100000 extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Create plugin table.';
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

        $this->addSql(<<<'SQL'
CREATE TABLE plugin (
    id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)',
    name VARCHAR(255) NOT NULL,
    description LONGTEXT NOT NULL,
    private TINYINT(1) NOT NULL DEFAULT '0',
    photo VARCHAR(255) NOT NULL COMMENT 'Plugin photo URL',
    enabled TINYINT(1) NOT NULL DEFAULT '1',
    created_at DATETIME DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
SQL
        );
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('DROP TABLE plugin');
    }
}
