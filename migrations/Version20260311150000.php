<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260311150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename chat_message.read to is_read to avoid SQL keyword conflicts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_message ADD is_read TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE chat_message SET is_read = CASE WHEN read_at IS NULL THEN 0 ELSE 1 END');
        $this->addSql('ALTER TABLE chat_message DROP `read`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_message ADD `read` TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE chat_message SET `read` = is_read');
        $this->addSql('ALTER TABLE chat_message DROP is_read');
    }
}
