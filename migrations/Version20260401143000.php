<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user game history and game level entry cost configuration tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE game_level_cost (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', game_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', level_key VARCHAR(16) NOT NULL, min_coins_cost BIGINT NOT NULL, created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uniq_game_level_cost_game_level (game_id, level_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('CREATE INDEX idx_game_level_cost_game_id ON game_level_cost (game_id)');
        $this->addSql('ALTER TABLE game_level_cost ADD CONSTRAINT FK_7D14C41BE48FD905 FOREIGN KEY (game_id) REFERENCES game_definition (id) ON DELETE CASCADE');

        $this->addSql("CREATE TABLE user_game (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', user_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', game_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', selected_level VARCHAR(16) NOT NULL, entry_cost_coins BIGINT NOT NULL, result VARCHAR(8) NOT NULL, reward_or_penalty_coins BIGINT NOT NULL, idempotency_key VARCHAR(100) DEFAULT NULL, created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX idx_user_game_user_created_at (user_id, created_at), UNIQUE INDEX uniq_user_game_user_idempotency (user_id, idempotency_key), INDEX idx_user_game_game_id (game_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE user_game ADD CONSTRAINT FK_89A8FB83A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_game ADD CONSTRAINT FK_89A8FB83E48FD905 FOREIGN KEY (game_id) REFERENCES game_definition (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_level_cost DROP FOREIGN KEY FK_7D14C41BE48FD905');
        $this->addSql('DROP TABLE game_level_cost');

        $this->addSql('ALTER TABLE user_game DROP FOREIGN KEY FK_89A8FB83A76ED395');
        $this->addSql('ALTER TABLE user_game DROP FOREIGN KEY FK_89A8FB83E48FD905');
        $this->addSql('DROP TABLE user_game');
    }
}
