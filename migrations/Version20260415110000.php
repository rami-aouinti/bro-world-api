<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Extend shop product with SEO/promotion/similarity metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE shop_product ADD texture VARCHAR(120) DEFAULT NULL, ADD promotion_percentage SMALLINT NOT NULL DEFAULT 0, ADD seo_title VARCHAR(255) DEFAULT NULL, ADD seo_description LONGTEXT DEFAULT NULL, ADD seo_keywords JSON NOT NULL COMMENT '(DC2Type:json)'");
        $this->addSql("UPDATE shop_product SET seo_keywords = '[]'");

        $this->addSql("CREATE TABLE shop_product_similarity (product_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', similar_product_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', INDEX IDX_EB0E08A24584665A (product_id), INDEX IDX_EB0E08A2EB959B11 (similar_product_id), PRIMARY KEY(product_id, similar_product_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE shop_product_similarity ADD CONSTRAINT FK_EB0E08A24584665A FOREIGN KEY (product_id) REFERENCES shop_product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shop_product_similarity ADD CONSTRAINT FK_EB0E08A2EB959B11 FOREIGN KEY (similar_product_id) REFERENCES shop_product (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shop_product_similarity DROP FOREIGN KEY FK_EB0E08A24584665A');
        $this->addSql('ALTER TABLE shop_product_similarity DROP FOREIGN KEY FK_EB0E08A2EB959B11');
        $this->addSql('DROP TABLE shop_product_similarity');
        $this->addSql('ALTER TABLE shop_product DROP texture, DROP promotion_percentage, DROP seo_title, DROP seo_description, DROP seo_keywords');
    }
}
