<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260314143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add indexes for recruit analytics queries.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_recruit_application_job_created_status ON recruit_application (job_id, created_at, status)');
        $this->addSql('CREATE INDEX idx_recruit_application_status_history_application_to_status_created_at ON recruit_application_status_history (application_id, to_status, created_at)');
        $this->addSql('CREATE INDEX idx_recruit_interview_application_created_at ON recruit_interview (application_id, created_at)');
        $this->addSql("CREATE TABLE recruit_offer (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', application_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', salary_proposed DOUBLE PRECISION NOT NULL, start_date DATE NOT NULL COMMENT '(DC2Type:date_immutable)', contract_type VARCHAR(25) NOT NULL, status VARCHAR(25) NOT NULL DEFAULT 'DRAFT', created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX idx_recruit_offer_application_created_at (application_id, created_at), INDEX IDX_DBC327B157698A6A (application_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE recruit_offer ADD CONSTRAINT FK_DBC327B157698A6A FOREIGN KEY (application_id) REFERENCES recruit_application (id) ON DELETE CASCADE');

        $this->addSql("CREATE TABLE recruit_offer_status_history (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', offer_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', author_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', action VARCHAR(50) NOT NULL, from_status VARCHAR(25) NOT NULL, to_status VARCHAR(25) NOT NULL, comment LONGTEXT DEFAULT NULL, created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX idx_recruit_offer_status_history_offer_created_at (offer_id, created_at), INDEX IDX_FD25235953C674EE (offer_id), INDEX IDX_FD252359F675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE recruit_offer_status_history ADD CONSTRAINT FK_FD25235953C674EE FOREIGN KEY (offer_id) REFERENCES recruit_offer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recruit_offer_status_history ADD CONSTRAINT FK_FD252359F675F31B FOREIGN KEY (author_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recruit_offer_status_history DROP FOREIGN KEY FK_FD25235953C674EE');
        $this->addSql('ALTER TABLE recruit_offer_status_history DROP FOREIGN KEY FK_FD252359F675F31B');
        $this->addSql('ALTER TABLE recruit_offer DROP FOREIGN KEY FK_DBC327B157698A6A');
        $this->addSql('DROP TABLE recruit_offer_status_history');
        $this->addSql('DROP TABLE recruit_offer');
    }
}
