<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260314120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add recruit interview feedback table with unique interviewer per interview constraint.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE recruit_interview_feedback (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', interview_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', interviewer_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', skills_score SMALLINT NOT NULL, communication_score SMALLINT NOT NULL, culture_fit_score SMALLINT NOT NULL, recommendation VARCHAR(30) NOT NULL, comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uq_recruit_feedback_interview_interviewer (interview_id, interviewer_id), INDEX idx_recruit_feedback_interview (interview_id), INDEX idx_recruit_feedback_interviewer (interviewer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE recruit_interview_feedback ADD CONSTRAINT FK_9CB42B90A7FDEB4A FOREIGN KEY (interview_id) REFERENCES recruit_interview (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recruit_interview_feedback ADD CONSTRAINT FK_9CB42B90DAC0C918 FOREIGN KEY (interviewer_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recruit_interview_feedback DROP FOREIGN KEY FK_9CB42B90A7FDEB4A');
        $this->addSql('ALTER TABLE recruit_interview_feedback DROP FOREIGN KEY FK_9CB42B90DAC0C918');
        $this->addSql('DROP TABLE recruit_interview_feedback');
    }
}
