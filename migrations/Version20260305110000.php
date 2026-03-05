<?php

declare(strict_types=1);

// phpcs:ignoreFile
namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

/**
 * Add photo field to user table.
 */
final class Version20260305110000 extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Add photo field to user table and generate default avatar URL from first and last name.';
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

        $this->addSql("ALTER TABLE user ADD photo VARCHAR(255) DEFAULT NULL COMMENT 'User profile photo URL' AFTER timezone");
        $this->addSql("UPDATE user SET photo = CONCAT('https://ui-avatars.com/api/?name=', REPLACE(CONCAT(first_name, ' ', last_name), ' ', '+')) WHERE photo IS NULL OR photo = ''");
        $this->addSql("ALTER TABLE user CHANGE photo photo VARCHAR(255) NOT NULL COMMENT 'User profile photo URL'");
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('ALTER TABLE user DROP photo');
    }
}
