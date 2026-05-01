<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260501100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add resume information photo column.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recruit_resume ADD information_photo VARCHAR(1024) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recruit_resume DROP information_photo');
    }
}
