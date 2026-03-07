<?php

declare(strict_types=1);

// phpcs:ignoreFile
namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260307110000 extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Create recruit module tables for jobs, companies, badges, tags and salaries';
    }

    #[Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('CREATE TABLE recruit_company (id BINARY(16) NOT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\", name VARCHAR(255) NOT NULL, logo VARCHAR(25) NOT NULL, sector VARCHAR(100) NOT NULL, size VARCHAR(100) NOT NULL, created_at DATETIME DEFAULT NULL COMMENT \"(DC2Type:datetime)\", updated_at DATETIME DEFAULT NULL COMMENT \"(DC2Type:datetime)\", PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE recruit_badge (id BINARY(16) NOT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\", label VARCHAR(255) NOT NULL, created_at DATETIME DEFAULT NULL COMMENT \"(DC2Type:datetime)\", updated_at DATETIME DEFAULT NULL COMMENT \"(DC2Type:datetime)\", PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE recruit_tag (id BINARY(16) NOT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\", label VARCHAR(100) NOT NULL, created_at DATETIME DEFAULT NULL COMMENT \"(DC2Type:datetime)\", updated_at DATETIME DEFAULT NULL COMMENT \"(DC2Type:datetime)\", PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE recruit_salary (id BINARY(16) NOT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\", min_salary INT NOT NULL, max_salary INT NOT NULL, currency VARCHAR(5) NOT NULL, period VARCHAR(20) NOT NULL, created_at DATETIME DEFAULT NULL COMMENT \"(DC2Type:datetime)\", updated_at DATETIME DEFAULT NULL COMMENT \"(DC2Type:datetime)\", PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE recruit_job (id BINARY(16) NOT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\", company_id BINARY(16) DEFAULT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\", salary_id BINARY(16) DEFAULT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\", slug VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, location VARCHAR(255) NOT NULL, contract_type VARCHAR(25) NOT NULL, work_mode VARCHAR(25) NOT NULL, schedule VARCHAR(25) NOT NULL, summary LONGTEXT NOT NULL, match_score SMALLINT NOT NULL, mission_title VARCHAR(255) NOT NULL, mission_description LONGTEXT NOT NULL, responsibilities JSON NOT NULL, profile JSON NOT NULL, benefits JSON NOT NULL, created_at DATETIME DEFAULT NULL COMMENT \"(DC2Type:datetime)\", updated_at DATETIME DEFAULT NULL COMMENT \"(DC2Type:datetime)\", INDEX IDX_D8F53A99979B1AD6 (company_id), UNIQUE INDEX UNIQ_D8F53A99A4594665 (salary_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE recruit_job_badge (job_id BINARY(16) NOT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\", badge_id BINARY(16) NOT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\", INDEX IDX_DCD31C48BE04EA9 (job_id), INDEX IDX_DCD31C4F7B2C80C (badge_id), PRIMARY KEY(job_id, badge_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE recruit_job_tag (job_id BINARY(16) NOT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\", tag_id BINARY(16) NOT NULL COMMENT \"(DC2Type:uuid_binary_ordered_time)\", INDEX IDX_7E0C1F02BE04EA9 (job_id), INDEX IDX_7E0C1F02BAD26311 (tag_id), PRIMARY KEY(job_id, tag_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE recruit_job ADD CONSTRAINT FK_D8F53A99979B1AD6 FOREIGN KEY (company_id) REFERENCES recruit_company (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE recruit_job ADD CONSTRAINT FK_D8F53A99A4594665 FOREIGN KEY (salary_id) REFERENCES recruit_salary (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE recruit_job_badge ADD CONSTRAINT FK_DCD31C48BE04EA9 FOREIGN KEY (job_id) REFERENCES recruit_job (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recruit_job_badge ADD CONSTRAINT FK_DCD31C4F7B2C80C FOREIGN KEY (badge_id) REFERENCES recruit_badge (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recruit_job_tag ADD CONSTRAINT FK_7E0C1F02BE04EA9 FOREIGN KEY (job_id) REFERENCES recruit_job (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recruit_job_tag ADD CONSTRAINT FK_7E0C1F02BAD26311 FOREIGN KEY (tag_id) REFERENCES recruit_tag (id) ON DELETE CASCADE');
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('ALTER TABLE recruit_job_badge DROP FOREIGN KEY FK_DCD31C48BE04EA9');
        $this->addSql('ALTER TABLE recruit_job_badge DROP FOREIGN KEY FK_DCD31C4F7B2C80C');
        $this->addSql('ALTER TABLE recruit_job_tag DROP FOREIGN KEY FK_7E0C1F02BE04EA9');
        $this->addSql('ALTER TABLE recruit_job_tag DROP FOREIGN KEY FK_7E0C1F02BAD26311');
        $this->addSql('ALTER TABLE recruit_job DROP FOREIGN KEY FK_D8F53A99979B1AD6');
        $this->addSql('ALTER TABLE recruit_job DROP FOREIGN KEY FK_D8F53A99A4594665');

        $this->addSql('DROP TABLE recruit_job_badge');
        $this->addSql('DROP TABLE recruit_job_tag');
        $this->addSql('DROP TABLE recruit_job');
        $this->addSql('DROP TABLE recruit_company');
        $this->addSql('DROP TABLE recruit_badge');
        $this->addSql('DROP TABLE recruit_tag');
        $this->addSql('DROP TABLE recruit_salary');
    }
}
