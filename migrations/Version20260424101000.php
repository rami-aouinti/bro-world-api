<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424101000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add many-to-many relation between blog posts and blog tags.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE blog_post_tag (post_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", tag_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", PRIMARY KEY(post_id, tag_id), INDEX IDX_6C91CB0A4B89032C (post_id), INDEX IDX_6C91CB0ABAD26311 (tag_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE blog_post_tag ADD CONSTRAINT FK_6C91CB0A4B89032C FOREIGN KEY (post_id) REFERENCES blog_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_post_tag ADD CONSTRAINT FK_6C91CB0ABAD26311 FOREIGN KEY (tag_id) REFERENCES blog_tag (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog_post_tag DROP FOREIGN KEY FK_6C91CB0A4B89032C');
        $this->addSql('ALTER TABLE blog_post_tag DROP FOREIGN KEY FK_6C91CB0ABAD26311');
        $this->addSql('DROP TABLE blog_post_tag');
    }
}
