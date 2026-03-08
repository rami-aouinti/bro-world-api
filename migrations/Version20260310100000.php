<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260310100000 extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Create blog and quiz plugin tables.';
    }

    #[Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Migration can only be executed safely on mysql.');

        $this->addSql('CREATE TABLE blog (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", application_id BINARY(16) DEFAULT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", owner_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", title VARCHAR(255) NOT NULL, type VARCHAR(20) NOT NULL, post_status VARCHAR(20) NOT NULL, comment_status VARCHAR(20) NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY(id), INDEX IDX_CFC77A65A3C9036F (application_id), INDEX IDX_CFC77A657E3C61F9 (owner_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE blog_post (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", blog_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", author_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", content LONGTEXT DEFAULT NULL, file_path VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY(id), INDEX IDX_3D2997B8B489D9B (blog_id), INDEX IDX_3D2997BF675F31B (author_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE blog_comment (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", post_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", parent_id BINARY(16) DEFAULT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", author_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", content LONGTEXT DEFAULT NULL, file_path VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY(id), INDEX IDX_5833B3B24B89032C (post_id), INDEX IDX_5833B3B2727ACA70 (parent_id), INDEX IDX_5833B3B2F675F31B (author_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE blog_reaction (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", comment_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", author_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", type VARCHAR(40) NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY(id), INDEX IDX_9C6D0F45F8697D13 (comment_id), INDEX IDX_9C6D0F45F675F31B (author_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE blog_tag (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", blog_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", label VARCHAR(100) NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY(id), INDEX IDX_9196940DB489D9B (blog_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE quiz (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", application_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", owner_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", configuration_id BINARY(16) DEFAULT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY(id), INDEX IDX_A412FA92A3C9036F (application_id), INDEX IDX_A412FA927E3C61F9 (owner_id), INDEX IDX_A412FA929DBB65DF (configuration_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE quiz_question (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", quiz_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", title LONGTEXT NOT NULL, level VARCHAR(50) NOT NULL, category VARCHAR(100) NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY(id), INDEX IDX_D2F9A39A853CD175 (quiz_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE quiz_answer (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", question_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", label LONGTEXT NOT NULL, correct TINYINT(1) NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY(id), INDEX IDX_EF6E868D1E27F6BF (question_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE blog ADD CONSTRAINT FK_CFC77A65A3C9036F FOREIGN KEY (application_id) REFERENCES platform_application (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog ADD CONSTRAINT FK_CFC77A657E3C61F9 FOREIGN KEY (owner_id) REFERENCES user_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_post ADD CONSTRAINT FK_3D2997B8B489D9B FOREIGN KEY (blog_id) REFERENCES blog (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_post ADD CONSTRAINT FK_3D2997BF675F31B FOREIGN KEY (author_id) REFERENCES user_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_comment ADD CONSTRAINT FK_5833B3B24B89032C FOREIGN KEY (post_id) REFERENCES blog_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_comment ADD CONSTRAINT FK_5833B3B2727ACA70 FOREIGN KEY (parent_id) REFERENCES blog_comment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_comment ADD CONSTRAINT FK_5833B3B2F675F31B FOREIGN KEY (author_id) REFERENCES user_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_reaction ADD CONSTRAINT FK_9C6D0F45F8697D13 FOREIGN KEY (comment_id) REFERENCES blog_comment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_reaction ADD CONSTRAINT FK_9C6D0F45F675F31B FOREIGN KEY (author_id) REFERENCES user_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_tag ADD CONSTRAINT FK_9196940DB489D9B FOREIGN KEY (blog_id) REFERENCES blog (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz ADD CONSTRAINT FK_A412FA92A3C9036F FOREIGN KEY (application_id) REFERENCES platform_application (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz ADD CONSTRAINT FK_A412FA927E3C61F9 FOREIGN KEY (owner_id) REFERENCES user_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz ADD CONSTRAINT FK_A412FA929DBB65DF FOREIGN KEY (configuration_id) REFERENCES configuration_configuration (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE quiz_question ADD CONSTRAINT FK_D2F9A39A853CD175 FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_answer ADD CONSTRAINT FK_EF6E868D1E27F6BF FOREIGN KEY (question_id) REFERENCES quiz_question (id) ON DELETE CASCADE');
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Migration can only be executed safely on mysql.');
        $this->addSql('ALTER TABLE blog_comment DROP FOREIGN KEY FK_5833B3B2727ACA70');
        $this->addSql('DROP TABLE quiz_answer');
        $this->addSql('DROP TABLE quiz_question');
        $this->addSql('DROP TABLE quiz');
        $this->addSql('DROP TABLE blog_reaction');
        $this->addSql('DROP TABLE blog_comment');
        $this->addSql('DROP TABLE blog_post');
        $this->addSql('DROP TABLE blog_tag');
        $this->addSql('DROP TABLE blog');
    }
}
