<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260311213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shop cart and cart item tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shop_cart (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", user_id BINARY(16) NOT NULL, shop_id BINARY(16) NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, subtotal DOUBLE PRECISION NOT NULL DEFAULT 0, items_count INT NOT NULL DEFAULT 0, created_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", INDEX idx_shop_cart_user_id (user_id), INDEX idx_shop_cart_shop_id (shop_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE shop_cart_item (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", cart_id BINARY(16) NOT NULL, product_id BINARY(16) NOT NULL, quantity INT NOT NULL DEFAULT 1, unit_price_snapshot DOUBLE PRECISION NOT NULL, line_total DOUBLE PRECISION NOT NULL, created_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", INDEX idx_shop_cart_item_product_id (product_id), INDEX idx_shop_cart_item_cart_id (cart_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE shop_cart ADD CONSTRAINT FK_898B0D12A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_cart ADD CONSTRAINT FK_898B0D124D16C4DD FOREIGN KEY (shop_id) REFERENCES shop (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE shop_cart_item ADD CONSTRAINT FK_55409D321AD5CDBF FOREIGN KEY (cart_id) REFERENCES shop_cart (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_cart_item ADD CONSTRAINT FK_55409D324584665A FOREIGN KEY (product_id) REFERENCES shop_product (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_cart_item DROP FOREIGN KEY FK_55409D321AD5CDBF');
        $this->addSql('ALTER TABLE shop_cart_item DROP FOREIGN KEY FK_55409D324584665A');
        $this->addSql('ALTER TABLE shop_cart DROP FOREIGN KEY FK_898B0D12A76ED395');
        $this->addSql('ALTER TABLE shop_cart DROP FOREIGN KEY FK_898B0D124D16C4DD');

        $this->addSql('DROP TABLE shop_cart_item');
        $this->addSql('DROP TABLE shop_cart');
    }
}
