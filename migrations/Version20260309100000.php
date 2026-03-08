<?php

declare(strict_types=1);

// phpcs:ignoreFile
namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260309100000 extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Create conversation_participant table to store chat conversation members.';
    }

    #[Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('CREATE TABLE conversation_participant (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid_binary_ordered_time)\', conversation_id BINARY(16) NOT NULL, user_id BINARY(16) NOT NULL, created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_conversation_participant_conversation_id (conversation_id), INDEX idx_conversation_participant_user_id (user_id), UNIQUE INDEX uq_conversation_participant_conversation_user (conversation_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT FK_C7A1A4B28E7927C9 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT FK_C7A1A4B2A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('ALTER TABLE conversation_participant DROP FOREIGN KEY FK_C7A1A4B28E7927C9');
        $this->addSql('ALTER TABLE conversation_participant DROP FOREIGN KEY FK_C7A1A4B2A76ED395');
        $this->addSql('DROP TABLE conversation_participant');
    }
}
