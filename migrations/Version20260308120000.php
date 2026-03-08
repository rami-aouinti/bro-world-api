<?php

declare(strict_types=1);

// phpcs:ignoreFile
namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260308120000 extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Add recruit applicant/application/resume domain with CV section entities';
    }

    #[Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('CREATE TABLE recruit_resume (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", owner_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", created_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime)", INDEX IDX_B4F6F8D57E3C61F9 (owner_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE recruit_applicant (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", user_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", resume_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", cover_letter LONGTEXT NOT NULL, created_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime)", UNIQUE INDEX UNIQ_A53D7BE939D0D039 (resume_id), INDEX IDX_A53D7BE9A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE recruit_application (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", applicant_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", job_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", status VARCHAR(25) NOT NULL DEFAULT "WAITING", created_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime)", INDEX IDX_2D3C03AFAE5E87E0 (applicant_id), INDEX IDX_2D3C03AFBE04EA9 (job_id), UNIQUE INDEX uq_recruit_application_applicant_job (applicant_id, job_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE recruit_resume_experience (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", resume_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, created_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime)", INDEX IDX_8E7D5A6E39D0D039 (resume_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE recruit_resume_education (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", resume_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, created_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime)", INDEX IDX_D4DE9A2639D0D039 (resume_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE recruit_resume_skill (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", resume_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, created_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime)", INDEX IDX_D8B8DE9039D0D039 (resume_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE recruit_resume_language (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", resume_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, created_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime)", INDEX IDX_C271844E39D0D039 (resume_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE recruit_resume_certification (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", resume_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, created_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime)", INDEX IDX_F4F9953B39D0D039 (resume_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE recruit_resume_project (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", resume_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, created_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime)", INDEX IDX_5468051139D0D039 (resume_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE recruit_resume_reference (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", resume_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, created_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime)", INDEX IDX_315D148939D0D039 (resume_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE recruit_resume_hobby (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", resume_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, created_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime)", INDEX IDX_89041E8A39D0D039 (resume_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE recruit_resume ADD CONSTRAINT FK_B4F6F8D57E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recruit_applicant ADD CONSTRAINT FK_A53D7BE9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recruit_applicant ADD CONSTRAINT FK_A53D7BE939D0D039 FOREIGN KEY (resume_id) REFERENCES recruit_resume (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recruit_application ADD CONSTRAINT FK_2D3C03AFAE5E87E0 FOREIGN KEY (applicant_id) REFERENCES recruit_applicant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recruit_application ADD CONSTRAINT FK_2D3C03AFBE04EA9 FOREIGN KEY (job_id) REFERENCES recruit_job (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE recruit_resume_experience ADD CONSTRAINT FK_8E7D5A6E39D0D039 FOREIGN KEY (resume_id) REFERENCES recruit_resume (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recruit_resume_education ADD CONSTRAINT FK_D4DE9A2639D0D039 FOREIGN KEY (resume_id) REFERENCES recruit_resume (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recruit_resume_skill ADD CONSTRAINT FK_D8B8DE9039D0D039 FOREIGN KEY (resume_id) REFERENCES recruit_resume (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recruit_resume_language ADD CONSTRAINT FK_C271844E39D0D039 FOREIGN KEY (resume_id) REFERENCES recruit_resume (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recruit_resume_certification ADD CONSTRAINT FK_F4F9953B39D0D039 FOREIGN KEY (resume_id) REFERENCES recruit_resume (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recruit_resume_project ADD CONSTRAINT FK_5468051139D0D039 FOREIGN KEY (resume_id) REFERENCES recruit_resume (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recruit_resume_reference ADD CONSTRAINT FK_315D148939D0D039 FOREIGN KEY (resume_id) REFERENCES recruit_resume (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recruit_resume_hobby ADD CONSTRAINT FK_89041E8A39D0D039 FOREIGN KEY (resume_id) REFERENCES recruit_resume (id) ON DELETE CASCADE');
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('ALTER TABLE recruit_application DROP FOREIGN KEY FK_2D3C03AFAE5E87E0');
        $this->addSql('ALTER TABLE recruit_application DROP FOREIGN KEY FK_2D3C03AFBE04EA9');
        $this->addSql('ALTER TABLE recruit_applicant DROP FOREIGN KEY FK_A53D7BE9A76ED395');
        $this->addSql('ALTER TABLE recruit_applicant DROP FOREIGN KEY FK_A53D7BE939D0D039');
        $this->addSql('ALTER TABLE recruit_resume DROP FOREIGN KEY FK_B4F6F8D57E3C61F9');

        $this->addSql('ALTER TABLE recruit_resume_experience DROP FOREIGN KEY FK_8E7D5A6E39D0D039');
        $this->addSql('ALTER TABLE recruit_resume_education DROP FOREIGN KEY FK_D4DE9A2639D0D039');
        $this->addSql('ALTER TABLE recruit_resume_skill DROP FOREIGN KEY FK_D8B8DE9039D0D039');
        $this->addSql('ALTER TABLE recruit_resume_language DROP FOREIGN KEY FK_C271844E39D0D039');
        $this->addSql('ALTER TABLE recruit_resume_certification DROP FOREIGN KEY FK_F4F9953B39D0D039');
        $this->addSql('ALTER TABLE recruit_resume_project DROP FOREIGN KEY FK_5468051139D0D039');
        $this->addSql('ALTER TABLE recruit_resume_reference DROP FOREIGN KEY FK_315D148939D0D039');
        $this->addSql('ALTER TABLE recruit_resume_hobby DROP FOREIGN KEY FK_89041E8A39D0D039');

        $this->addSql('DROP TABLE recruit_application');
        $this->addSql('DROP TABLE recruit_applicant');
        $this->addSql('DROP TABLE recruit_resume');

        $this->addSql('DROP TABLE recruit_resume_experience');
        $this->addSql('DROP TABLE recruit_resume_education');
        $this->addSql('DROP TABLE recruit_resume_skill');
        $this->addSql('DROP TABLE recruit_resume_language');
        $this->addSql('DROP TABLE recruit_resume_certification');
        $this->addSql('DROP TABLE recruit_resume_project');
        $this->addSql('DROP TABLE recruit_resume_reference');
        $this->addSql('DROP TABLE recruit_resume_hobby');
    }
}
