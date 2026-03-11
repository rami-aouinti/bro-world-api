<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260311233000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow blog reactions on posts in addition to comments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog_reaction ADD post_id BINARY(16) DEFAULT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)"');
        $this->addSql('ALTER TABLE blog_reaction CHANGE comment_id comment_id BINARY(16) DEFAULT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)"');
        $this->addSql('CREATE INDEX IDX_9C6D0F454B89032C ON blog_reaction (post_id)');
        $this->addSql('ALTER TABLE blog_reaction ADD CONSTRAINT FK_9C6D0F454B89032C FOREIGN KEY (post_id) REFERENCES blog_post (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog_reaction DROP FOREIGN KEY FK_9C6D0F454B89032C');
        $this->addSql('DROP INDEX IDX_9C6D0F454B89032C ON blog_reaction');
        $this->addSql('ALTER TABLE blog_reaction DROP post_id');
        $this->addSql('ALTER TABLE blog_reaction CHANGE comment_id comment_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)"');
    }
}
