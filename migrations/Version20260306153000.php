<?php

declare(strict_types=1);

// phpcs:ignoreFile
namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

/**
 * Add user-owned applications with platform, plugins and scoped configurations.
 */
final class Version20260306153000 extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Create platform_application and platform_application_plugin tables and link configuration to both.';
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

        $this->addSql("CREATE TABLE platform_application (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', user_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', platform_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', title VARCHAR(255) NOT NULL, status VARCHAR(25) NOT NULL DEFAULT 'active', created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL ON UPDATE CURRENT_TIMESTAMP, INDEX idx_platform_application_user_id (user_id), INDEX idx_platform_application_platform_id (platform_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE platform_application ADD CONSTRAINT FK_PLATFORM_APPLICATION_USER_ID FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE platform_application ADD CONSTRAINT FK_PLATFORM_APPLICATION_PLATFORM_ID FOREIGN KEY (platform_id) REFERENCES platform (id) ON DELETE RESTRICT');

        $this->addSql("CREATE TABLE platform_application_plugin (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', application_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', plugin_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL ON UPDATE CURRENT_TIMESTAMP, INDEX idx_platform_application_plugin_application_id (application_id), INDEX idx_platform_application_plugin_plugin_id (plugin_id), UNIQUE INDEX uq_application_plugin (application_id, plugin_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE platform_application_plugin ADD CONSTRAINT FK_PLATFORM_APPLICATION_PLUGIN_APPLICATION_ID FOREIGN KEY (application_id) REFERENCES platform_application (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE platform_application_plugin ADD CONSTRAINT FK_PLATFORM_APPLICATION_PLUGIN_PLUGIN_ID FOREIGN KEY (plugin_id) REFERENCES plugin (id) ON DELETE RESTRICT');

        $this->addSql("ALTER TABLE configuration ADD application_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', ADD application_plugin_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)'");
        $this->addSql('CREATE INDEX idx_configuration_application_id ON configuration (application_id)');
        $this->addSql('CREATE INDEX idx_configuration_application_plugin_id ON configuration (application_plugin_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_configuration_application_key ON configuration (application_id, configuration_key)');
        $this->addSql('CREATE UNIQUE INDEX uq_configuration_application_plugin_key ON configuration (application_plugin_id, configuration_key)');
        $this->addSql('ALTER TABLE configuration ADD CONSTRAINT FK_CONFIGURATION_APPLICATION_ID FOREIGN KEY (application_id) REFERENCES platform_application (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE configuration ADD CONSTRAINT FK_CONFIGURATION_APPLICATION_PLUGIN_ID FOREIGN KEY (application_plugin_id) REFERENCES platform_application_plugin (id) ON DELETE CASCADE');
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('ALTER TABLE configuration DROP FOREIGN KEY FK_CONFIGURATION_APPLICATION_ID');
        $this->addSql('ALTER TABLE configuration DROP FOREIGN KEY FK_CONFIGURATION_APPLICATION_PLUGIN_ID');
        $this->addSql('DROP INDEX idx_configuration_application_id ON configuration');
        $this->addSql('DROP INDEX idx_configuration_application_plugin_id ON configuration');
        $this->addSql('DROP INDEX uq_configuration_application_key ON configuration');
        $this->addSql('DROP INDEX uq_configuration_application_plugin_key ON configuration');
        $this->addSql('ALTER TABLE configuration DROP application_id, DROP application_plugin_id');

        $this->addSql('ALTER TABLE platform_application_plugin DROP FOREIGN KEY FK_PLATFORM_APPLICATION_PLUGIN_APPLICATION_ID');
        $this->addSql('ALTER TABLE platform_application_plugin DROP FOREIGN KEY FK_PLATFORM_APPLICATION_PLUGIN_PLUGIN_ID');
        $this->addSql('DROP TABLE platform_application_plugin');

        $this->addSql('ALTER TABLE platform_application DROP FOREIGN KEY FK_PLATFORM_APPLICATION_USER_ID');
        $this->addSql('ALTER TABLE platform_application DROP FOREIGN KEY FK_PLATFORM_APPLICATION_PLATFORM_ID');
        $this->addSql('DROP TABLE platform_application');
    }
}
