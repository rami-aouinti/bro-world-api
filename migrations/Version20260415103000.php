<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add global shop scope support via nullable application relation and is_global flag.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop ADD is_global TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('CREATE INDEX idx_shop_is_global ON shop (is_global)');
        $this->addSql('ALTER TABLE shop CHANGE application_id application_id BINARY(16) DEFAULT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)"');

        $this->addSql('UPDATE shop SET is_global = 1 WHERE application_id IS NULL');
    }

    public function down(Schema $schema): void
    {
        $globalRows = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM shop WHERE is_global = 1');
        $this->abortIf($globalRows > 0, 'Cannot revert migration: shop contains at least one global scope row.');

        $rowsWithNullApplication = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM shop WHERE application_id IS NULL');
        $this->abortIf($rowsWithNullApplication > 0, 'Cannot revert migration: shop contains rows with NULL application_id.');

        $this->addSql('ALTER TABLE shop CHANGE application_id application_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)"');
        $this->addSql('DROP INDEX idx_shop_is_global ON shop');
        $this->addSql('ALTER TABLE shop DROP is_global');
    }
}
