<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user coins balance with default value and range constraint.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD coins BIGINT NOT NULL DEFAULT 5000');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT CHK_USER_COINS_RANGE CHECK (coins >= 0 AND coins <= 100000000000)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP CHECK CHK_USER_COINS_RANGE');
        $this->addSql('ALTER TABLE user DROP coins');
    }
}
