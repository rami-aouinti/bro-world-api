<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260313100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user stories table for 24h stories.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_story (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", user_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", image_url VARCHAR(1024) NOT NULL, expires_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", created_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", INDEX IDX_67CC82ECA76ED395 (user_id), INDEX IDX_67CC82EC6F7D7EFB (created_at), INDEX IDX_67CC82ECEA9FDD75 (expires_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_story ADD CONSTRAINT FK_67CC82ECA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_story DROP FOREIGN KEY FK_67CC82ECA76ED395');
        $this->addSql('DROP TABLE user_story');
    }
}
