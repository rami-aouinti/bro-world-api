<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add resume information title field.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recruit_resume ADD information_title VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recruit_resume DROP information_title');
    }
}
