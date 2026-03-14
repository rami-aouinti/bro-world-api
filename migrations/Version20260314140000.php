<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260314140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add chat listing performance indexes for conversation, message and participant tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_chat_conversation_chat_archived_last_created ON chat_conversation (chat_id, archived_at, last_message_at, created_at)');
        $this->addSql('CREATE INDEX idx_chat_message_conversation_deleted_created ON chat_message (conversation_id, deleted_at, created_at)');
        $this->addSql('CREATE INDEX idx_chat_conversation_participant_user_conversation ON chat_conversation_participant (user_id, conversation_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_chat_conversation_chat_archived_last_created ON chat_conversation');
        $this->addSql('DROP INDEX idx_chat_message_conversation_deleted_created ON chat_message');
        $this->addSql('DROP INDEX idx_chat_conversation_participant_user_conversation ON chat_conversation_participant');
    }
}
