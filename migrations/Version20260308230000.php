<?php

declare(strict_types=1);

// phpcs:ignoreFile
namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20260308230000 extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Attach calendar and chat to platform application for plugin provisioning.';
    }

    #[Override]
    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql("ALTER TABLE calendar ADD application_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)'");
        $this->addSql('CREATE INDEX IDX_FC84F7D83E030ACD ON calendar (application_id)');
        $this->addSql('ALTER TABLE calendar ADD CONSTRAINT FK_FC84F7D83E030ACD FOREIGN KEY (application_id) REFERENCES platform_application (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE chat DROP FOREIGN KEY FK_5A9A02C5E030ACD');
        $this->addSql('ALTER TABLE chat ADD CONSTRAINT FK_5A9A02C5E030ACD FOREIGN KEY (application_id) REFERENCES platform_application (id) ON DELETE CASCADE');
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('ALTER TABLE chat DROP FOREIGN KEY FK_5A9A02C5E030ACD');
        $this->addSql('ALTER TABLE chat ADD CONSTRAINT FK_5A9A02C5E030ACD FOREIGN KEY (application_id) REFERENCES recruit_application (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE calendar DROP FOREIGN KEY FK_FC84F7D83E030ACD');
        $this->addSql('DROP INDEX IDX_FC84F7D83E030ACD ON calendar');
        $this->addSql('ALTER TABLE calendar DROP application_id');
    }
}
