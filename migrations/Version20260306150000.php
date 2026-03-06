<?php

declare(strict_types=1);

// phpcs:ignoreFile
namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

/**
 * Add platform status and ownership with plugin/configuration relations.
 */
final class Version20260306150000 extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Add platform status/user ownership, platform-plugin relation, and configuration links for platform/plugin scopes.';
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

        $this->addSql("ALTER TABLE platform ADD user_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', ADD status VARCHAR(25) NOT NULL DEFAULT 'active'");
        $this->addSql('CREATE INDEX idx_platform_user_id ON platform (user_id)');
        $this->addSql('ALTER TABLE platform ADD CONSTRAINT FK_PLATFORM_USER_ID FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');

        $this->addSql("ALTER TABLE plugin ADD platform_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)'");
        $this->addSql('CREATE INDEX idx_plugin_platform_id ON plugin (platform_id)');
        $this->addSql('ALTER TABLE plugin ADD CONSTRAINT FK_PLUGIN_PLATFORM_ID FOREIGN KEY (platform_id) REFERENCES platform (id) ON DELETE CASCADE');

        $this->addSql("ALTER TABLE configuration ADD platform_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', ADD plugin_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)'");
        $this->addSql('CREATE INDEX idx_configuration_platform_id ON configuration (platform_id)');
        $this->addSql('CREATE INDEX idx_configuration_plugin_id ON configuration (plugin_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_configuration_platform_key ON configuration (platform_id, configuration_key)');
        $this->addSql('CREATE UNIQUE INDEX uq_configuration_plugin_key ON configuration (plugin_id, configuration_key)');
        $this->addSql('ALTER TABLE configuration ADD CONSTRAINT FK_CONFIGURATION_PLATFORM_ID FOREIGN KEY (platform_id) REFERENCES platform (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE configuration ADD CONSTRAINT FK_CONFIGURATION_PLUGIN_ID FOREIGN KEY (plugin_id) REFERENCES plugin (id) ON DELETE CASCADE');
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('ALTER TABLE configuration DROP FOREIGN KEY FK_CONFIGURATION_PLATFORM_ID');
        $this->addSql('ALTER TABLE configuration DROP FOREIGN KEY FK_CONFIGURATION_PLUGIN_ID');
        $this->addSql('DROP INDEX idx_configuration_platform_id ON configuration');
        $this->addSql('DROP INDEX idx_configuration_plugin_id ON configuration');
        $this->addSql('DROP INDEX uq_configuration_platform_key ON configuration');
        $this->addSql('DROP INDEX uq_configuration_plugin_key ON configuration');
        $this->addSql('ALTER TABLE configuration DROP platform_id, DROP plugin_id');

        $this->addSql('ALTER TABLE plugin DROP FOREIGN KEY FK_PLUGIN_PLATFORM_ID');
        $this->addSql('DROP INDEX idx_plugin_platform_id ON plugin');
        $this->addSql('ALTER TABLE plugin DROP platform_id');

        $this->addSql('ALTER TABLE platform DROP FOREIGN KEY FK_PLATFORM_USER_ID');
        $this->addSql('DROP INDEX idx_platform_user_id ON platform');
        $this->addSql('ALTER TABLE platform DROP user_id, DROP status');
    }
}
