<?php

declare(strict_types=1);

// phpcs:ignoreFile
namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260307123000 extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Add recruit entity linked to application and attach jobs to recruit';
    }

    #[Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('CREATE TABLE recruit (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", application_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", created_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime)", UNIQUE INDEX UNIQ_2E2FD9DC3E030ACD (application_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE recruit ADD CONSTRAINT FK_2E2FD9DC3E030ACD FOREIGN KEY (application_id) REFERENCES platform_application (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE recruit_job ADD recruit_id BINARY(16) DEFAULT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)"');
        $this->addSql('CREATE INDEX IDX_D8F53A9946EA0B10 ON recruit_job (recruit_id)');
        $this->addSql('ALTER TABLE recruit_job ADD CONSTRAINT FK_D8F53A9946EA0B10 FOREIGN KEY (recruit_id) REFERENCES recruit (id) ON DELETE CASCADE');
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('ALTER TABLE recruit_job DROP FOREIGN KEY FK_D8F53A9946EA0B10');
        $this->addSql('DROP INDEX IDX_D8F53A9946EA0B10 ON recruit_job');
        $this->addSql('ALTER TABLE recruit_job DROP recruit_id');

        $this->addSql('ALTER TABLE recruit DROP FOREIGN KEY FK_2E2FD9DC3E030ACD');
        $this->addSql('DROP TABLE recruit');
    }
}
