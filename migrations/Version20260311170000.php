<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260311170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Refactor quiz module schema with metadata fields, ordering columns, and dedicated indexes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE quiz ADD title VARCHAR(255) DEFAULT 'Application quiz' NOT NULL, ADD description LONGTEXT DEFAULT '' NOT NULL, ADD is_published TINYINT(1) DEFAULT 0 NOT NULL, ADD pass_score INT DEFAULT 70 NOT NULL");
        $this->addSql('CREATE INDEX idx_quiz_application_id ON quiz (application_id)');
        $this->addSql('CREATE INDEX idx_quiz_is_published ON quiz (is_published)');

        $this->addSql('ALTER TABLE quiz_question ADD position INT DEFAULT 1 NOT NULL, ADD points INT DEFAULT 1 NOT NULL, ADD explanation LONGTEXT DEFAULT NULL');
        $this->addSql("UPDATE quiz_question SET category = LOWER(category), level = LOWER(level)");
        $this->addSql("UPDATE quiz_question SET category = 'general' WHERE category NOT IN ('general', 'backend', 'frontend', 'devops', 'onboarding')");
        $this->addSql("UPDATE quiz_question SET level = 'easy' WHERE level NOT IN ('easy', 'medium', 'hard')");
        $this->addSql('CREATE INDEX idx_quiz_question_level ON quiz_question (level)');
        $this->addSql('CREATE INDEX idx_quiz_question_category ON quiz_question (category)');
        $this->addSql('CREATE INDEX idx_quiz_question_position ON quiz_question (position)');

        $this->addSql('ALTER TABLE quiz_answer ADD position INT DEFAULT 1 NOT NULL');
        $this->addSql('CREATE INDEX idx_quiz_answer_question_id ON quiz_answer (question_id)');
        $this->addSql('CREATE INDEX idx_quiz_answer_position ON quiz_answer (position)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_quiz_answer_question_id ON quiz_answer');
        $this->addSql('DROP INDEX idx_quiz_answer_position ON quiz_answer');
        $this->addSql('ALTER TABLE quiz_answer DROP position');

        $this->addSql('DROP INDEX idx_quiz_question_level ON quiz_question');
        $this->addSql('DROP INDEX idx_quiz_question_category ON quiz_question');
        $this->addSql('DROP INDEX idx_quiz_question_position ON quiz_question');
        $this->addSql('ALTER TABLE quiz_question DROP position, DROP points, DROP explanation');

        $this->addSql('DROP INDEX idx_quiz_application_id ON quiz');
        $this->addSql('DROP INDEX idx_quiz_is_published ON quiz');
        $this->addSql('ALTER TABLE quiz DROP title, DROP description, DROP is_published, DROP pass_score');
    }
}
