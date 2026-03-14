<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260314143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add indexes for recruit analytics queries.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_recruit_application_job_created_status ON recruit_application (job_id, created_at, status)');
        $this->addSql('CREATE INDEX idx_recruit_application_status_history_application_to_status_created_at ON recruit_application_status_history (application_id, to_status, created_at)');
        $this->addSql('CREATE INDEX idx_recruit_interview_application_created_at ON recruit_interview (application_id, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_recruit_application_job_created_status ON recruit_application');
        $this->addSql('DROP INDEX idx_recruit_application_status_history_application_to_status_created_at ON recruit_application_status_history');
        $this->addSql('DROP INDEX idx_recruit_interview_application_created_at ON recruit_interview');
    }
}
