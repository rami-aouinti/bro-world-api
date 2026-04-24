<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'User visibility/subscription flags and abonnement_news table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD visible TINYINT(1) NOT NULL DEFAULT 1, ADD abonnement TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('CREATE TABLE abonnement_news (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, execute_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", executed TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE INDEX idx_abonnement_news_execute ON abonnement_news (execute_at, executed)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE abonnement_news');
        $this->addSql('ALTER TABLE user DROP visible, DROP abonnement');
    }
}
