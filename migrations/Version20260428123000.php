<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add resume birth/profile fields and skill level.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recruit_resume ADD information_birth_date DATE DEFAULT NULL COMMENT "(DC2Type:date_immutable)", ADD information_birth_place VARCHAR(255) DEFAULT NULL, ADD information_profile_text LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE recruit_resume_skill ADD level VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recruit_resume DROP information_birth_date, DROP information_birth_place, DROP information_profile_text');
        $this->addSql('ALTER TABLE recruit_resume_skill DROP level');
    }
}
