<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260311210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enforce unique blog reaction per (comment_id, author_id)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog_reaction ADD CONSTRAINT uniq_blog_reaction_comment_author UNIQUE (comment_id, author_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog_reaction DROP INDEX uniq_blog_reaction_comment_author');
    }
}
