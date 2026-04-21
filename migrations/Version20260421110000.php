<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Attach dedicated quizzes to school_exam and recruit_job entities.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE school_exam ADD quiz_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid_binary_ordered_time)\'');
        $this->addSql('ALTER TABLE recruit_job ADD quiz_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid_binary_ordered_time)\'');
        $this->addSql('CREATE INDEX idx_school_exam_quiz_id ON school_exam (quiz_id)');
        $this->addSql('CREATE INDEX idx_recruit_job_quiz_id ON recruit_job (quiz_id)');
        $this->addSql('ALTER TABLE school_exam ADD CONSTRAINT FK_F41E5E86853CD175 FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE recruit_job ADD CONSTRAINT FK_75CF03EA853CD175 FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE school_exam DROP FOREIGN KEY FK_F41E5E86853CD175');
        $this->addSql('ALTER TABLE recruit_job DROP FOREIGN KEY FK_75CF03EA853CD175');
        $this->addSql('DROP INDEX idx_school_exam_quiz_id ON school_exam');
        $this->addSql('DROP INDEX idx_recruit_job_quiz_id ON recruit_job');
        $this->addSql('ALTER TABLE school_exam DROP quiz_id');
        $this->addSql('ALTER TABLE recruit_job DROP quiz_id');
    }
}
