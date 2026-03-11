<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260311160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recruit job enrichments: experience metadata and publication lifecycle';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE recruit_job ADD experience_level VARCHAR(25) DEFAULT 'Mid' NOT NULL, ADD years_experience_min SMALLINT DEFAULT 0 NOT NULL, ADD years_experience_max SMALLINT DEFAULT 0 NOT NULL, ADD is_published TINYINT(1) DEFAULT 1 NOT NULL, ADD published_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql('UPDATE recruit_job SET published_at = created_at WHERE is_published = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recruit_job DROP experience_level, DROP years_experience_min, DROP years_experience_max, DROP is_published, DROP published_at');
    }
}
