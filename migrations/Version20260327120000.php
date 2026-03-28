<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260327120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user library folders and files tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE library_folder (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', owner_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', parent_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', name VARCHAR(255) NOT NULL, created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_39B15F027E3C61F9 (owner_id), INDEX IDX_39B15F01727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE library_file (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', owner_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', folder_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', name VARCHAR(255) NOT NULL, url VARCHAR(255) NOT NULL, mime_type VARCHAR(120) NOT NULL, size INT NOT NULL, extension VARCHAR(20) NOT NULL, file_type VARCHAR(20) NOT NULL, created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_B4A96E4C7E3C61F9 (owner_id), INDEX IDX_B4A96E4C162CB942 (folder_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE library_folder ADD CONSTRAINT FK_39B15F027E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE library_folder ADD CONSTRAINT FK_39B15F01727ACA70 FOREIGN KEY (parent_id) REFERENCES library_folder (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE library_file ADD CONSTRAINT FK_B4A96E4C7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE library_file ADD CONSTRAINT FK_B4A96E4C162CB942 FOREIGN KEY (folder_id) REFERENCES library_folder (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE library_file DROP FOREIGN KEY FK_B4A96E4C7E3C61F9');
        $this->addSql('ALTER TABLE library_file DROP FOREIGN KEY FK_B4A96E4C162CB942');
        $this->addSql('ALTER TABLE library_folder DROP FOREIGN KEY FK_39B15F027E3C61F9');
        $this->addSql('ALTER TABLE library_folder DROP FOREIGN KEY FK_39B15F01727ACA70');
        $this->addSql('DROP TABLE library_file');
        $this->addSql('DROP TABLE library_folder');
    }
}
