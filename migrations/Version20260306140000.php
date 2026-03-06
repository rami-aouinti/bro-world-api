<?php

declare(strict_types=1);

// phpcs:ignoreFile
namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

/**
 * Add user relation to configuration and switch uniqueness to (user_id, configuration_key).
 */
final class Version20260306140000 extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Add nullable user_id to configuration with FK and unique index on (user_id, configuration_key).';
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

        $this->addSql("ALTER TABLE configuration ADD user_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)'");
        $this->addSql('ALTER TABLE configuration DROP INDEX uq_configuration_key');
        $this->addSql('CREATE INDEX idx_configuration_user_id ON configuration (user_id)');
        $this->addSql('ALTER TABLE configuration ADD CONSTRAINT FK_CONFIGURATION_USER_ID FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX uq_configuration_user_key ON configuration (user_id, configuration_key)');
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('ALTER TABLE configuration DROP FOREIGN KEY FK_CONFIGURATION_USER_ID');
        $this->addSql('DROP INDEX uq_configuration_user_key ON configuration');
        $this->addSql('DROP INDEX idx_configuration_user_id ON configuration');
        $this->addSql('CREATE UNIQUE INDEX uq_configuration_key ON configuration (configuration_key)');
        $this->addSql('ALTER TABLE configuration DROP user_id');
    }
}
