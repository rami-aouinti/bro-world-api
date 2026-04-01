<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add game sub-categories and public payload fields for categories and games.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE game_sub_category (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", category_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", sub_category_key VARCHAR(100) NOT NULL, name_key VARCHAR(255) NOT NULL, description_key VARCHAR(255) DEFAULT NULL, img VARCHAR(255) DEFAULT NULL, icon VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", UNIQUE INDEX UNIQ_GAME_SUB_CATEGORY_KEY (sub_category_key), INDEX IDX_GAME_SUB_CATEGORY_CATEGORY (category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE game_category CHANGE name name_key VARCHAR(255) NOT NULL, CHANGE description description_key VARCHAR(255) DEFAULT NULL, CHANGE category_key category_key VARCHAR(100) NOT NULL, ADD img VARCHAR(255) DEFAULT NULL, ADD icon VARCHAR(255) DEFAULT NULL');

        $this->addSql('ALTER TABLE game_definition CHANGE name name_key VARCHAR(255) NOT NULL, ADD sub_category_id BINARY(16) DEFAULT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", ADD game_key VARCHAR(100) DEFAULT NULL, ADD description_key VARCHAR(255) DEFAULT NULL, ADD img VARCHAR(255) DEFAULT NULL, ADD icon VARCHAR(255) DEFAULT NULL, ADD component VARCHAR(255) DEFAULT NULL, ADD supported_modes JSON DEFAULT NULL, ADD category_key VARCHAR(100) DEFAULT NULL, ADD subcategory_key VARCHAR(100) DEFAULT NULL, ADD difficulty_key VARCHAR(100) DEFAULT NULL, ADD tags JSON DEFAULT NULL, ADD features JSON DEFAULT NULL');
        $this->addSql('UPDATE game_definition SET game_key = LOWER(REPLACE(name_key, " ", "-")) WHERE game_key IS NULL');
        $this->addSql('UPDATE game_definition SET supported_modes = JSON_ARRAY(), tags = JSON_ARRAY(), features = JSON_ARRAY() WHERE supported_modes IS NULL OR tags IS NULL OR features IS NULL');
        $this->addSql('ALTER TABLE game_definition MODIFY game_key VARCHAR(100) NOT NULL, MODIFY supported_modes JSON NOT NULL, MODIFY tags JSON NOT NULL, MODIFY features JSON NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_GAME_DEFINITION_KEY ON game_definition (game_key)');
        $this->addSql('CREATE INDEX IDX_GAME_DEFINITION_SUB_CATEGORY ON game_definition (sub_category_id)');

        $this->addSql('ALTER TABLE game_sub_category ADD CONSTRAINT FK_GAME_SUB_CATEGORY_CATEGORY FOREIGN KEY (category_id) REFERENCES game_category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE game_definition ADD CONSTRAINT FK_GAME_DEFINITION_SUB_CATEGORY FOREIGN KEY (sub_category_id) REFERENCES game_sub_category (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_definition DROP FOREIGN KEY FK_GAME_DEFINITION_SUB_CATEGORY');
        $this->addSql('ALTER TABLE game_sub_category DROP FOREIGN KEY FK_GAME_SUB_CATEGORY_CATEGORY');

        $this->addSql('DROP INDEX UNIQ_GAME_DEFINITION_KEY ON game_definition');
        $this->addSql('DROP INDEX IDX_GAME_DEFINITION_SUB_CATEGORY ON game_definition');
        $this->addSql('ALTER TABLE game_definition CHANGE name_key name VARCHAR(255) NOT NULL, DROP sub_category_id, DROP game_key, DROP description_key, DROP img, DROP icon, DROP component, DROP supported_modes, DROP category_key, DROP subcategory_key, DROP difficulty_key, DROP tags, DROP features');

        $this->addSql('ALTER TABLE game_category CHANGE name_key name VARCHAR(255) NOT NULL, CHANGE description_key description LONGTEXT NOT NULL, CHANGE category_key category_key VARCHAR(50) NOT NULL, DROP img, DROP icon');

        $this->addSql('DROP TABLE game_sub_category');
    }
}
