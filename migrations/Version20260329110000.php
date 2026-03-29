<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260329110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Game module tables with relations and performance indexes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE game_category (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", name VARCHAR(255) NOT NULL, category_key VARCHAR(50) NOT NULL, description LONGTEXT NOT NULL, created_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", UNIQUE INDEX UNIQ_GAME_CATEGORY_KEY (category_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE game_definition (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", category_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", name VARCHAR(255) NOT NULL, metadata JSON NOT NULL, level VARCHAR(25) NOT NULL, status VARCHAR(25) NOT NULL, created_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", INDEX IDX_GAME_DEFINITION_CATEGORY (category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE game_session (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", game_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", user_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", status VARCHAR(25) NOT NULL, started_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", ended_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", context JSON NOT NULL, created_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", INDEX IDX_GAME_SESSION_GAME (game_id), INDEX IDX_GAME_SESSION_USER (user_id), INDEX idx_game_session_game_created_at (game_id, created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE game_score (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", session_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", value INT NOT NULL, breakdown JSON NOT NULL, calculated_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", created_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", INDEX IDX_GAME_SCORE_SESSION (session_id), INDEX idx_game_score_session_calculated_at (session_id, calculated_at), INDEX idx_game_score_session_value (session_id, value), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE game_statistic (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", game_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", user_id BINARY(16) DEFAULT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", stat_key VARCHAR(100) NOT NULL, stat_value DOUBLE PRECISION NOT NULL, metadata JSON NOT NULL, recorded_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", created_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", INDEX IDX_GAME_STATISTIC_GAME (game_id), INDEX IDX_GAME_STATISTIC_USER (user_id), INDEX idx_game_statistic_user_game (user_id, game_id), INDEX idx_game_statistic_game_value (game_id, stat_value), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE game_definition ADD CONSTRAINT FK_GAME_DEFINITION_CATEGORY FOREIGN KEY (category_id) REFERENCES game_category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE game_session ADD CONSTRAINT FK_GAME_SESSION_GAME FOREIGN KEY (game_id) REFERENCES game_definition (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE game_session ADD CONSTRAINT FK_GAME_SESSION_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE game_score ADD CONSTRAINT FK_GAME_SCORE_SESSION FOREIGN KEY (session_id) REFERENCES game_session (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE game_statistic ADD CONSTRAINT FK_GAME_STATISTIC_GAME FOREIGN KEY (game_id) REFERENCES game_definition (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE game_statistic ADD CONSTRAINT FK_GAME_STATISTIC_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_definition DROP FOREIGN KEY FK_GAME_DEFINITION_CATEGORY');
        $this->addSql('ALTER TABLE game_session DROP FOREIGN KEY FK_GAME_SESSION_GAME');
        $this->addSql('ALTER TABLE game_session DROP FOREIGN KEY FK_GAME_SESSION_USER');
        $this->addSql('ALTER TABLE game_score DROP FOREIGN KEY FK_GAME_SCORE_SESSION');
        $this->addSql('ALTER TABLE game_statistic DROP FOREIGN KEY FK_GAME_STATISTIC_GAME');
        $this->addSql('ALTER TABLE game_statistic DROP FOREIGN KEY FK_GAME_STATISTIC_USER');

        $this->addSql('DROP TABLE game_score');
        $this->addSql('DROP TABLE game_session');
        $this->addSql('DROP TABLE game_statistic');
        $this->addSql('DROP TABLE game_definition');
        $this->addSql('DROP TABLE game_category');
    }
}
