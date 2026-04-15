<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add photo URL columns to shop_category and shop_product.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE shop_category ADD photo VARCHAR(1024) NOT NULL DEFAULT ''");
        $this->addSql("ALTER TABLE shop_product ADD photo VARCHAR(1024) NOT NULL DEFAULT ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_product DROP photo');
        $this->addSql('ALTER TABLE shop_category DROP photo');
    }
}
