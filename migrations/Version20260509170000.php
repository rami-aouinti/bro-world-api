<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260509170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add new recruit template config json fields for resume, cover page and cover letter';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recruit_template ADD photo_options JSON DEFAULT NULL, ADD level_style JSON DEFAULT NULL, ADD section_order JSON DEFAULT NULL, ADD section_types JSON DEFAULT NULL, ADD hero JSON DEFAULT NULL, ADD design_tokens JSON DEFAULT NULL, ADD design_config JSON DEFAULT NULL, ADD default_values JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recruit_template DROP photo_options, DROP level_style, DROP section_order, DROP section_types, DROP hero, DROP design_tokens, DROP design_config, DROP default_values');
    }
}
