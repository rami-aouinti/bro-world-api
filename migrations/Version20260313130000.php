<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260313130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Strengthen blog reaction integrity with unique and target constraints.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uniq_blog_reaction_author_comment ON blog_reaction (author_id, comment_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_blog_reaction_author_post ON blog_reaction (author_id, post_id)');
        $this->addSql('ALTER TABLE blog_reaction ADD CONSTRAINT chk_blog_reaction_exactly_one_target CHECK ((comment_id IS NOT NULL AND post_id IS NULL) OR (comment_id IS NULL AND post_id IS NOT NULL))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog_reaction DROP CHECK chk_blog_reaction_exactly_one_target');
        $this->addSql('DROP INDEX uniq_blog_reaction_author_comment ON blog_reaction');
        $this->addSql('DROP INDEX uniq_blog_reaction_author_post ON blog_reaction');
    }
}
