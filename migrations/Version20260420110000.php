<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'School domain: student/teacher linked to user, add courses and learning session notes, and relate exams/grades to courses.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE school_course (id BINARY(16) NOT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\", class_id BINARY(16) NOT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\", teacher_id BINARY(16) DEFAULT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\", name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \"(DC2Type:utcdatetime)\", updated_at DATETIME NOT NULL COMMENT \"(DC2Type:utcdatetime)\", INDEX idx_school_course_class_id (class_id), INDEX idx_school_course_teacher_id (teacher_id), UNIQUE INDEX uniq_school_course_class_name (class_id, name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE school_learning_session_note (id BINARY(16) NOT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\", student_id BINARY(16) NOT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\", exam_id BINARY(16) NOT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\", course_id BINARY(16) NOT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\", score DOUBLE PRECISION NOT NULL, passed TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \"(DC2Type:utcdatetime)\", updated_at DATETIME NOT NULL COMMENT \"(DC2Type:utcdatetime)\", INDEX idx_school_lsn_student_id (student_id), INDEX idx_school_lsn_exam_id (exam_id), INDEX idx_school_lsn_course_id (course_id), UNIQUE INDEX uniq_school_lsn_exam_student (exam_id, student_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE school_student ADD user_id BINARY(16) DEFAULT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\"');
        $this->addSql('ALTER TABLE school_teacher ADD user_id BINARY(16) DEFAULT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\"');
        $this->addSql('ALTER TABLE school_exam ADD course_id BINARY(16) DEFAULT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\"');
        $this->addSql('ALTER TABLE school_grade ADD course_id BINARY(16) DEFAULT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\"');

        $this->addSql('UPDATE school_student SET user_id = (SELECT id FROM user LIMIT 1) WHERE user_id IS NULL');
        $this->addSql('UPDATE school_teacher SET user_id = (SELECT id FROM user LIMIT 1) WHERE user_id IS NULL');

        $this->addSql('ALTER TABLE school_student MODIFY user_id BINARY(16) NOT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\"');
        $this->addSql('ALTER TABLE school_teacher MODIFY user_id BINARY(16) NOT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\"');

        $this->addSql('ALTER TABLE school_student DROP name');
        $this->addSql('ALTER TABLE school_teacher DROP name');

        $this->addSql('ALTER TABLE school_course ADD CONSTRAINT FK_B6F245838A98A09F FOREIGN KEY (class_id) REFERENCES school_class (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE school_course ADD CONSTRAINT FK_B6F2458341807C73 FOREIGN KEY (teacher_id) REFERENCES school_teacher (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE school_learning_session_note ADD CONSTRAINT FK_AA9E2E08CB944F1A FOREIGN KEY (student_id) REFERENCES school_student (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE school_learning_session_note ADD CONSTRAINT FK_AA9E2E08A5D7E69F FOREIGN KEY (exam_id) REFERENCES school_exam (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE school_learning_session_note ADD CONSTRAINT FK_AA9E2E08591CC992 FOREIGN KEY (course_id) REFERENCES school_course (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE school_student ADD CONSTRAINT FK_DF77A88BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE school_teacher ADD CONSTRAINT FK_3A1BFCDA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');

        $this->addSql('CREATE INDEX idx_school_student_user_id ON school_student (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_school_student_user_class ON school_student (user_id, class_id)');
        $this->addSql('CREATE INDEX idx_school_teacher_user_id ON school_teacher (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_school_teacher_user ON school_teacher (user_id)');

        $this->addSql('INSERT INTO school_course (id, class_id, teacher_id, name, created_at, updated_at)
            SELECT UUID_TO_BIN(UUID(), 1), sc.id, (
                SELECT sct.teacher_id FROM school_class_teacher sct WHERE sct.school_class_id = sc.id LIMIT 1
            ), CONCAT(sc.name, " - Core"), NOW(), NOW() FROM school_class sc');

        $this->addSql('UPDATE school_exam se
            JOIN school_course c ON c.class_id = se.class_id
            SET se.course_id = c.id
            WHERE se.course_id IS NULL');
        $this->addSql('UPDATE school_grade sg
            JOIN school_exam se ON se.id = sg.exam_id
            SET sg.course_id = se.course_id
            WHERE sg.course_id IS NULL');

        $this->addSql('ALTER TABLE school_exam MODIFY course_id BINARY(16) NOT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\"');
        $this->addSql('ALTER TABLE school_grade MODIFY course_id BINARY(16) NOT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\"');

        $this->addSql('ALTER TABLE school_exam ADD CONSTRAINT FK_39C8D6F3591CC992 FOREIGN KEY (course_id) REFERENCES school_course (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE school_grade ADD CONSTRAINT FK_DCA4BFD4591CC992 FOREIGN KEY (course_id) REFERENCES school_course (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_school_exam_course_id ON school_exam (course_id)');
        $this->addSql('CREATE INDEX idx_school_grade_course_id ON school_grade (course_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE school_grade DROP FOREIGN KEY FK_DCA4BFD4591CC992');
        $this->addSql('ALTER TABLE school_exam DROP FOREIGN KEY FK_39C8D6F3591CC992');
        $this->addSql('DROP INDEX idx_school_grade_course_id ON school_grade');
        $this->addSql('DROP INDEX idx_school_exam_course_id ON school_exam');
        $this->addSql('ALTER TABLE school_grade DROP course_id');
        $this->addSql('ALTER TABLE school_exam DROP course_id');

        $this->addSql('DROP INDEX uniq_school_teacher_user ON school_teacher');
        $this->addSql('DROP INDEX idx_school_teacher_user_id ON school_teacher');
        $this->addSql('DROP INDEX uniq_school_student_user_class ON school_student');
        $this->addSql('DROP INDEX idx_school_student_user_id ON school_student');

        $this->addSql('ALTER TABLE school_teacher DROP FOREIGN KEY FK_3A1BFCDA76ED395');
        $this->addSql('ALTER TABLE school_student DROP FOREIGN KEY FK_DF77A88BA76ED395');

        $this->addSql('ALTER TABLE school_teacher ADD name VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE school_student ADD name VARCHAR(255) NOT NULL');
        $this->addSql("UPDATE school_teacher SET name = 'Teacher'");
        $this->addSql("UPDATE school_student SET name = 'Student'");

        $this->addSql('ALTER TABLE school_teacher DROP user_id');
        $this->addSql('ALTER TABLE school_student DROP user_id');

        $this->addSql('ALTER TABLE school_learning_session_note DROP FOREIGN KEY FK_AA9E2E08591CC992');
        $this->addSql('ALTER TABLE school_learning_session_note DROP FOREIGN KEY FK_AA9E2E08A5D7E69F');
        $this->addSql('ALTER TABLE school_learning_session_note DROP FOREIGN KEY FK_AA9E2E08CB944F1A');
        $this->addSql('ALTER TABLE school_course DROP FOREIGN KEY FK_B6F2458341807C73');
        $this->addSql('ALTER TABLE school_course DROP FOREIGN KEY FK_B6F245838A98A09F');
        $this->addSql('DROP TABLE school_learning_session_note');
        $this->addSql('DROP TABLE school_course');
    }
}
