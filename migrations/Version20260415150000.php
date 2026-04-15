<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track coins credit idempotence and timestamp on payment transactions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_payment_transaction ADD coins_credit_reference VARCHAR(190) DEFAULT NULL, ADD coins_credited_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)"');
        $this->addSql('CREATE UNIQUE INDEX uniq_shop_payment_coins_credit_reference ON shop_payment_transaction (coins_credit_reference)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_shop_payment_coins_credit_reference ON shop_payment_transaction');
        $this->addSql('ALTER TABLE shop_payment_transaction DROP coins_credit_reference, DROP coins_credited_at');
    }
}
