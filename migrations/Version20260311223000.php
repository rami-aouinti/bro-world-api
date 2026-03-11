<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260311223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shop order and shop order item tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shop_order (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", user_id BINARY(16) NOT NULL, shop_id BINARY(16) NOT NULL, status VARCHAR(30) NOT NULL, subtotal DOUBLE PRECISION NOT NULL, billing_address LONGTEXT NOT NULL, shipping_address LONGTEXT NOT NULL, email VARCHAR(190) NOT NULL, phone VARCHAR(40) NOT NULL, shipping_method VARCHAR(80) NOT NULL, created_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", INDEX idx_shop_order_status (status), INDEX idx_shop_order_created_at (created_at), INDEX idx_shop_order_shop_id (shop_id), INDEX idx_shop_order_user_id (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE shop_order_item (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", order_id BINARY(16) NOT NULL, product_id BINARY(16) NOT NULL, quantity INT NOT NULL, unit_price_snapshot DOUBLE PRECISION NOT NULL, line_total DOUBLE PRECISION NOT NULL, product_name_snapshot VARCHAR(255) NOT NULL, product_sku_snapshot VARCHAR(64) NOT NULL, created_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", updated_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", INDEX idx_shop_order_item_order_id (order_id), INDEX idx_shop_order_item_product_id (product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE shop_order ADD CONSTRAINT FK_6D84A701A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_order ADD CONSTRAINT FK_6D84A7014D16C4DD FOREIGN KEY (shop_id) REFERENCES shop (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE shop_order_item ADD CONSTRAINT FK_6FC3D4A58D9F6D38 FOREIGN KEY (order_id) REFERENCES shop_order (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_order_item ADD CONSTRAINT FK_6FC3D4A54584665A FOREIGN KEY (product_id) REFERENCES shop_product (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_order_item DROP FOREIGN KEY FK_6FC3D4A58D9F6D38');
        $this->addSql('ALTER TABLE shop_order_item DROP FOREIGN KEY FK_6FC3D4A54584665A');
        $this->addSql('ALTER TABLE shop_order DROP FOREIGN KEY FK_6D84A701A76ED395');
        $this->addSql('ALTER TABLE shop_order DROP FOREIGN KEY FK_6D84A7014D16C4DD');

        $this->addSql('DROP TABLE shop_order_item');
        $this->addSql('DROP TABLE shop_order');
    }
}
