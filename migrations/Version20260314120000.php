<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260314120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add quiz attempt persistence tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE quiz_attempt (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", quiz_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", user_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", score DOUBLE PRECISION NOT NULL, passed TINYINT(1) NOT NULL, total_questions INT NOT NULL, correct_answers INT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY(id), INDEX idx_quiz_attempt_quiz_id (quiz_id), INDEX idx_quiz_attempt_user_id (user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE quiz_attempt_answer (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", attempt_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", question_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", selected_answer_id BINARY(16) DEFAULT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", is_correct TINYINT(1) NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY(id), INDEX idx_quiz_attempt_answer_attempt_id (attempt_id), INDEX idx_quiz_attempt_answer_question_id (question_id), INDEX IDX_C6F8D8B796996A7C (selected_answer_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE quiz_attempt ADD CONSTRAINT FK_A7B4229D853CD175 FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_attempt ADD CONSTRAINT FK_A7B4229DA76ED395 FOREIGN KEY (user_id) REFERENCES user_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_attempt_answer ADD CONSTRAINT FK_C6F8D8B77D453CEC FOREIGN KEY (attempt_id) REFERENCES quiz_attempt (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_attempt_answer ADD CONSTRAINT FK_C6F8D8B71E27F6BF FOREIGN KEY (question_id) REFERENCES quiz_question (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_attempt_answer ADD CONSTRAINT FK_C6F8D8B796996A7C FOREIGN KEY (selected_answer_id) REFERENCES quiz_answer (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quiz_attempt_answer DROP FOREIGN KEY FK_C6F8D8B77D453CEC');
        $this->addSql('ALTER TABLE quiz_attempt_answer DROP FOREIGN KEY FK_C6F8D8B71E27F6BF');
        $this->addSql('ALTER TABLE quiz_attempt_answer DROP FOREIGN KEY FK_C6F8D8B796996A7C');
        $this->addSql('ALTER TABLE quiz_attempt DROP FOREIGN KEY FK_A7B4229D853CD175');
        $this->addSql('ALTER TABLE quiz_attempt DROP FOREIGN KEY FK_A7B4229DA76ED395');
        $this->addSql('DROP TABLE quiz_attempt_answer');
        $this->addSql('DROP TABLE quiz_attempt');
    }
}
