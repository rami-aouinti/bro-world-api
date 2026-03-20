<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260320101000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create quiz_category entity and link quiz_question to category_id.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE quiz_category (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', name VARCHAR(120) NOT NULL, slug VARCHAR(120) NOT NULL, position INT DEFAULT 1 NOT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_2A53CA72989D9B62 (slug), INDEX idx_quiz_category_slug (slug), INDEX idx_quiz_category_active (is_active), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql('ALTER TABLE quiz_question ADD category_id BINARY(16) DEFAULT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\"');

        $this->addSql("INSERT INTO quiz_category (id, name, slug, position, is_active, created_at) VALUES
            (UUID_TO_BIN(UUID(), 1), 'General Knowledge', 'general', 1, 1, NOW()),
            (UUID_TO_BIN(UUID(), 1), 'Backend', 'backend', 2, 1, NOW()),
            (UUID_TO_BIN(UUID(), 1), 'Frontend', 'frontend', 3, 1, NOW()),
            (UUID_TO_BIN(UUID(), 1), 'DevOps', 'devops', 4, 1, NOW()),
            (UUID_TO_BIN(UUID(), 1), 'Onboarding', 'onboarding', 5, 1, NOW()),
            (UUID_TO_BIN(UUID(), 1), 'Data', 'data', 6, 1, NOW()),
            (UUID_TO_BIN(UUID(), 1), 'Security', 'security', 7, 1, NOW()),
            (UUID_TO_BIN(UUID(), 1), 'Architecture', 'architecture', 8, 1, NOW()),
            (UUID_TO_BIN(UUID(), 1), 'Mobile', 'mobile', 9, 1, NOW()),
            (UUID_TO_BIN(UUID(), 1), 'Testing', 'testing', 10, 1, NOW())");

        $this->addSql('UPDATE quiz_question qq INNER JOIN quiz_category qc ON qc.slug = qq.category SET qq.category_id = qc.id');
        $this->addSql("UPDATE quiz_question qq INNER JOIN quiz_category qc ON qc.slug = 'general' SET qq.category_id = qc.id WHERE qq.category_id IS NULL");

        $this->addSql('ALTER TABLE quiz_question DROP INDEX idx_quiz_question_category');
        $this->addSql('ALTER TABLE quiz_question DROP category');
        $this->addSql('ALTER TABLE quiz_question CHANGE category_id category_id BINARY(16) NOT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\"');
        $this->addSql('ALTER TABLE quiz_question ADD CONSTRAINT FK_6E4C2C9812469DE2 FOREIGN KEY (category_id) REFERENCES quiz_category (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX idx_quiz_question_category ON quiz_question (category_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE quiz_question ADD category VARCHAR(100) DEFAULT 'general' NOT NULL");
        $this->addSql('UPDATE quiz_question qq INNER JOIN quiz_category qc ON qq.category_id = qc.id SET qq.category = qc.slug');
        $this->addSql('ALTER TABLE quiz_question DROP FOREIGN KEY FK_6E4C2C9812469DE2');
        $this->addSql('ALTER TABLE quiz_question DROP INDEX idx_quiz_question_category');
        $this->addSql('ALTER TABLE quiz_question DROP category_id');
        $this->addSql('CREATE INDEX idx_quiz_question_category ON quiz_question (category)');

        $this->addSql('DROP TABLE quiz_category');
    }
}
