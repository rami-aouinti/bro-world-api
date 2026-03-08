<?php

declare(strict_types=1);

// phpcs:ignoreFile
namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

/**
 * Add owner relation to recruit_job.
 */
final class Version20260308183000 extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Add owner_id to recruit_job and backfill from recruit application owner.';
    }

    #[Override]
    public function isTransactional(): bool
    {
        return false;
    }

    #[Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql("ALTER TABLE recruit_job ADD owner_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)'");
        $this->addSql('CREATE INDEX IDX_D76F4BDA7E3C61F9 ON recruit_job (owner_id)');

        $this->addSql('UPDATE recruit_job job INNER JOIN recruit recruit ON recruit.id = job.recruit_id INNER JOIN platform_application application ON application.id = recruit.application_id SET job.owner_id = application.user_id WHERE job.owner_id IS NULL');

        $this->addSql('ALTER TABLE recruit_job CHANGE owner_id owner_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)"');
        $this->addSql('ALTER TABLE recruit_job ADD CONSTRAINT FK_D76F4BDA7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('ALTER TABLE recruit_job DROP FOREIGN KEY FK_D76F4BDA7E3C61F9');
        $this->addSql('DROP INDEX IDX_D76F4BDA7E3C61F9 ON recruit_job');
        $this->addSql('ALTER TABLE recruit_job DROP owner_id');
    }
}
