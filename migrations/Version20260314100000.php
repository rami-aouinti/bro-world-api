<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260314100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Introduce canonical recruit application status pipeline and persist status transition history.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE recruit_application SET status = 'SCREENING' WHERE status IN ('IN_PROGRESS', 'DISCUSSION')");
        $this->addSql("UPDATE recruit_application SET status = 'INTERVIEW_PLANNED' WHERE status = 'INVITE_TO_INTERVIEW'");
        $this->addSql("UPDATE recruit_application SET status = 'INTERVIEW_DONE' WHERE status = 'INTERVIEW'");
        $this->addSql("UPDATE recruit_application SET status = 'HIRED' WHERE status = 'ACCEPTED'");

        $this->addSql("CREATE TABLE recruit_application_status_history (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', application_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', author_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', from_status VARCHAR(25) NOT NULL, to_status VARCHAR(25) NOT NULL, comment LONGTEXT DEFAULT NULL, created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX idx_recruit_application_status_history_application_created_at (application_id, created_at), INDEX IDX_RECRUIT_APPLICATION_STATUS_HISTORY_APPLICATION_ID (application_id), INDEX IDX_RECRUIT_APPLICATION_STATUS_HISTORY_AUTHOR_ID (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE recruit_application_status_history ADD CONSTRAINT FK_RECRUIT_APPLICATION_STATUS_HISTORY_APPLICATION_ID FOREIGN KEY (application_id) REFERENCES recruit_application (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recruit_application_status_history ADD CONSTRAINT FK_RECRUIT_APPLICATION_STATUS_HISTORY_AUTHOR_ID FOREIGN KEY (author_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recruit_application_status_history DROP FOREIGN KEY FK_RECRUIT_APPLICATION_STATUS_HISTORY_APPLICATION_ID');
        $this->addSql('ALTER TABLE recruit_application_status_history DROP FOREIGN KEY FK_RECRUIT_APPLICATION_STATUS_HISTORY_AUTHOR_ID');
        $this->addSql('DROP TABLE recruit_application_status_history');

        $this->addSql("UPDATE recruit_application SET status = 'IN_PROGRESS' WHERE status = 'SCREENING'");
        $this->addSql("UPDATE recruit_application SET status = 'INVITE_TO_INTERVIEW' WHERE status = 'INTERVIEW_PLANNED'");
        $this->addSql("UPDATE recruit_application SET status = 'INTERVIEW' WHERE status = 'INTERVIEW_DONE'");
        $this->addSql("UPDATE recruit_application SET status = 'ACCEPTED' WHERE status = 'HIRED'");
    }
}
