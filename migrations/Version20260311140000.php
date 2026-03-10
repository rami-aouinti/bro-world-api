<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260311140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add read boolean flag on chat messages with default false';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_message ADD is_read TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE chat_message SET is_read = CASE WHEN read_at IS NULL THEN 0 ELSE 1 END');
        $this->addSql('ALTER TABLE chat_conversation DROP INDEX uq_conversation_chat_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_conversation ADD CONSTRAINT uq_conversation_chat_id UNIQUE (chat_id)');
        $this->addSql('ALTER TABLE chat_message DROP is_read');
    }
}
