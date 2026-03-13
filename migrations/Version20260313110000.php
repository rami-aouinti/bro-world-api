<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260313110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow recruit applicant resume relation to be nullable.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recruit_applicant CHANGE resume_id resume_id BINARY(16) DEFAULT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)"');
    }

    public function down(Schema $schema): void
    {
        $rowsWithNullResume = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM recruit_applicant WHERE resume_id IS NULL');
        $this->abortIf($rowsWithNullResume > 0, 'Cannot revert migration: recruit_applicant contains rows with NULL resume_id.');

        $this->addSql('ALTER TABLE recruit_applicant CHANGE resume_id resume_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)"');
    }
}
