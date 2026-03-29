<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260329123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create game level options taxonomy table for public listing and CRUD.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE game_level_option (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", level_value VARCHAR(25) NOT NULL, label VARCHAR(50) NOT NULL, description LONGTEXT NOT NULL, created_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", UNIQUE INDEX UNIQ_GAME_LEVEL_OPTION_VALUE (level_value), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE game_level_option');
    }
}
