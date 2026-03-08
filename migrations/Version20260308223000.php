<?php

declare(strict_types=1);

// phpcs:ignoreFile
namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260308223000 extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Create chat and conversation tables with chat context relation.';
    }

    #[Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('CREATE TABLE chat (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid_binary_ordered_time)\', application_id BINARY(16) NOT NULL, application_slug VARCHAR(100) NOT NULL, created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_chat_application_slug (application_slug), INDEX IDX_5A9A02C5E030ACD (application_id), UNIQUE INDEX uq_chat_application_slug (application_slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE conversation (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid_binary_ordered_time)\', chat_id BINARY(16) NOT NULL, application_slug VARCHAR(100) NOT NULL, created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_conversation_chat_id (chat_id), INDEX idx_conversation_application_slug (application_slug), UNIQUE INDEX uq_conversation_chat_application_slug (chat_id, application_slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE chat ADD CONSTRAINT FK_5A9A02C5E030ACD FOREIGN KEY (application_id) REFERENCES recruit_application (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8E7927C9117A0B0B FOREIGN KEY (chat_id) REFERENCES chat (id) ON DELETE CASCADE');
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8E7927C9117A0B0B');
        $this->addSql('ALTER TABLE chat DROP FOREIGN KEY FK_5A9A02C5E030ACD');
        $this->addSql('DROP TABLE conversation');
        $this->addSql('DROP TABLE chat');
    }
}
