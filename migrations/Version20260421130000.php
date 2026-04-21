<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Page: add storage for public contact requests.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE page_public_contact_request (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, email VARCHAR(190) NOT NULL, type VARCHAR(100) NOT NULL, message LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE INDEX idx_page_public_contact_request_email ON page_public_contact_request (email)');
        $this->addSql('CREATE INDEX idx_page_public_contact_request_created_at ON page_public_contact_request (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE page_public_contact_request');
    }
}
