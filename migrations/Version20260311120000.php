<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260311120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user friend relation table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_friend_relation (id BINARY(16) NOT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\", requester_id BINARY(16) NOT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\", addressee_id BINARY(16) NOT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\", status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_user_friend_relation_requester_id (requester_id), INDEX idx_user_friend_relation_addressee_id (addressee_id), UNIQUE INDEX uq_user_friend_relation_requester_addressee (requester_id, addressee_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_friend_relation ADD CONSTRAINT FK_2AF903A4A1F7A876 FOREIGN KEY (requester_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_friend_relation ADD CONSTRAINT FK_2AF903A4B6A26353 FOREIGN KEY (addressee_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_friend_relation');
    }
}
