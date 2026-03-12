<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260312120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add blog post slug, parent relation, shared url and media urls.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog_post ADD slug VARCHAR(255) DEFAULT "" NOT NULL, ADD media_urls JSON DEFAULT NULL, ADD shared_url VARCHAR(1024) DEFAULT NULL, ADD parent_post_id BINARY(16) DEFAULT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)"');
        $this->addSql('UPDATE blog_post SET slug = CONCAT("post-", LOWER(HEX(id))) WHERE slug = ""');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3D2997B8989D9B62 ON blog_post (slug)');
        $this->addSql('CREATE INDEX IDX_3D2997B8AE0BD594 ON blog_post (parent_post_id)');
        $this->addSql('ALTER TABLE blog_post ADD CONSTRAINT FK_3D2997B8AE0BD594 FOREIGN KEY (parent_post_id) REFERENCES blog_post (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog_post DROP FOREIGN KEY FK_3D2997B8AE0BD594');
        $this->addSql('DROP INDEX IDX_3D2997B8AE0BD594 ON blog_post');
        $this->addSql('DROP INDEX UNIQ_3D2997B8989D9B62 ON blog_post');
        $this->addSql('ALTER TABLE blog_post DROP slug, DROP media_urls, DROP shared_url, DROP parent_post_id');
    }
}
