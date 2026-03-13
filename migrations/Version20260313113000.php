<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260313113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store shop monetary fields as integer cents.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE shop_product SET price = ROUND(price * 100)');
        $this->addSql('UPDATE shop_cart SET subtotal = ROUND(subtotal * 100)');
        $this->addSql('UPDATE shop_cart_item SET unit_price_snapshot = ROUND(unit_price_snapshot * 100), line_total = ROUND(line_total * 100)');
        $this->addSql('UPDATE shop_order SET subtotal = ROUND(subtotal * 100)');
        $this->addSql('UPDATE shop_order_item SET unit_price_snapshot = ROUND(unit_price_snapshot * 100), line_total = ROUND(line_total * 100)');
        $this->addSql('UPDATE shop_payment_transaction SET amount = ROUND(amount * 100)');

        $this->addSql('ALTER TABLE shop_product MODIFY price INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE shop_cart MODIFY subtotal INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE shop_cart_item MODIFY unit_price_snapshot INT NOT NULL DEFAULT 0, MODIFY line_total INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE shop_order MODIFY subtotal INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE shop_order_item MODIFY unit_price_snapshot INT NOT NULL DEFAULT 0, MODIFY line_total INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE shop_payment_transaction MODIFY amount INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_product MODIFY price DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE shop_cart MODIFY subtotal DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE shop_cart_item MODIFY unit_price_snapshot DOUBLE PRECISION NOT NULL, MODIFY line_total DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE shop_order MODIFY subtotal DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE shop_order_item MODIFY unit_price_snapshot DOUBLE PRECISION NOT NULL, MODIFY line_total DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE shop_payment_transaction MODIFY amount DOUBLE PRECISION NOT NULL');

        $this->addSql('UPDATE shop_product SET price = ROUND(price / 100, 2)');
        $this->addSql('UPDATE shop_cart SET subtotal = ROUND(subtotal / 100, 2)');
        $this->addSql('UPDATE shop_cart_item SET unit_price_snapshot = ROUND(unit_price_snapshot / 100, 2), line_total = ROUND(line_total / 100, 2)');
        $this->addSql('UPDATE shop_order SET subtotal = ROUND(subtotal / 100, 2)');
        $this->addSql('UPDATE shop_order_item SET unit_price_snapshot = ROUND(unit_price_snapshot / 100, 2), line_total = ROUND(line_total / 100, 2)');
        $this->addSql('UPDATE shop_payment_transaction SET amount = ROUND(amount / 100, 2)');
    }
}
