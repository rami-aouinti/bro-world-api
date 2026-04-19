<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Extend recruit resume schema with contact information and rich section metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recruit_resume ADD information_full_name VARCHAR(255) DEFAULT NULL, ADD information_email VARCHAR(255) DEFAULT NULL, ADD information_phone VARCHAR(50) DEFAULT NULL, ADD information_homepage VARCHAR(255) DEFAULT NULL, ADD information_repo_profile VARCHAR(255) DEFAULT NULL, ADD information_address VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE recruit_resume_certification ADD attachments JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE recruit_resume_education ADD school VARCHAR(255) DEFAULT NULL, ADD start_date DATE DEFAULT NULL COMMENT "(DC2Type:date_immutable)", ADD end_date DATE DEFAULT NULL COMMENT "(DC2Type:date_immutable)", ADD location VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE recruit_resume_experience ADD company VARCHAR(255) DEFAULT NULL, ADD start_date DATE DEFAULT NULL COMMENT "(DC2Type:date_immutable)", ADD end_date DATE DEFAULT NULL COMMENT "(DC2Type:date_immutable)"');
        $this->addSql('ALTER TABLE recruit_resume_language ADD level VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE recruit_resume_project ADD attachments JSON DEFAULT NULL, ADD home_page VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recruit_resume_project DROP attachments, DROP home_page');
        $this->addSql('ALTER TABLE recruit_resume_language DROP level');
        $this->addSql('ALTER TABLE recruit_resume_experience DROP company, DROP start_date, DROP end_date');
        $this->addSql('ALTER TABLE recruit_resume_education DROP school, DROP start_date, DROP end_date, DROP location');
        $this->addSql('ALTER TABLE recruit_resume_certification DROP attachments');
        $this->addSql('ALTER TABLE recruit_resume DROP information_full_name, DROP information_email, DROP information_phone, DROP information_homepage, DROP information_repo_profile, DROP information_address');
    }
}
