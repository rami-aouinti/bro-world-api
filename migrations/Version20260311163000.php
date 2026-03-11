<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260311163000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Improve blog architecture with metadata fields, post pinning and reaction enum support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE blog ADD slug VARCHAR(150) DEFAULT '' NOT NULL, ADD description LONGTEXT DEFAULT NULL, ADD visibility VARCHAR(20) DEFAULT 'public' NOT NULL");
        $this->addSql('UPDATE blog SET slug = CASE WHEN type = "general" THEN "general" ELSE CONCAT("application-blog-", LOWER(HEX(id))) END');
        $this->addSql('ALTER TABLE blog ADD CONSTRAINT UNIQ_189DE4E3989D9B62 UNIQUE (slug)');

        $this->addSql("ALTER TABLE blog_post ADD title VARCHAR(255) DEFAULT '' NOT NULL, ADD is_pinned TINYINT(1) DEFAULT 0 NOT NULL");
        $this->addSql('UPDATE blog_post SET title = LEFT(COALESCE(content, ""), 255)');

        $this->addSql("ALTER TABLE blog_reaction CHANGE type type VARCHAR(40) DEFAULT 'like' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog DROP INDEX UNIQ_189DE4E3989D9B62');
        $this->addSql('ALTER TABLE blog DROP slug, DROP description, DROP visibility');

        $this->addSql('ALTER TABLE blog_post DROP title, DROP is_pinned');
        $this->addSql("ALTER TABLE blog_reaction CHANGE type type VARCHAR(40) NOT NULL");
    }
}
