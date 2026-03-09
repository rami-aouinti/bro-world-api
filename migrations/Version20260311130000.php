<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260311130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Introduce dedicated page language table and connect CMS pages to it';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE page_language (id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)", code VARCHAR(10) NOT NULL, label VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uq_page_language_code (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE page_home DROP language, ADD language_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)"');
        $this->addSql('CREATE INDEX idx_page_home_language_id ON page_home (language_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_page_home_language_id ON page_home (language_id)');
        $this->addSql('ALTER TABLE page_home ADD CONSTRAINT FK_4BF8D00482F1BAF4 FOREIGN KEY (language_id) REFERENCES page_language (id)');

        $this->addSql('ALTER TABLE page_about DROP language, ADD language_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)"');
        $this->addSql('CREATE INDEX idx_page_about_language_id ON page_about (language_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_page_about_language_id ON page_about (language_id)');
        $this->addSql('ALTER TABLE page_about ADD CONSTRAINT FK_7AFC3D8282F1BAF4 FOREIGN KEY (language_id) REFERENCES page_language (id)');

        $this->addSql('ALTER TABLE page_contact DROP language, ADD language_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)"');
        $this->addSql('CREATE INDEX idx_page_contact_language_id ON page_contact (language_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_page_contact_language_id ON page_contact (language_id)');
        $this->addSql('ALTER TABLE page_contact ADD CONSTRAINT FK_C8EE6D4782F1BAF4 FOREIGN KEY (language_id) REFERENCES page_language (id)');

        $this->addSql('ALTER TABLE page_faq DROP language, ADD language_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid_binary_ordered_time)"');
        $this->addSql('CREATE INDEX idx_page_faq_language_id ON page_faq (language_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_page_faq_language_id ON page_faq (language_id)');
        $this->addSql('ALTER TABLE page_faq ADD CONSTRAINT FK_C743A75C82F1BAF4 FOREIGN KEY (language_id) REFERENCES page_language (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE page_home DROP FOREIGN KEY FK_4BF8D00482F1BAF4');
        $this->addSql('DROP INDEX uq_page_home_language_id ON page_home');
        $this->addSql('DROP INDEX idx_page_home_language_id ON page_home');
        $this->addSql('ALTER TABLE page_home DROP language_id, ADD language VARCHAR(2) NOT NULL COMMENT "(DC2Type:EnumLanguage)"');

        $this->addSql('ALTER TABLE page_about DROP FOREIGN KEY FK_7AFC3D8282F1BAF4');
        $this->addSql('DROP INDEX uq_page_about_language_id ON page_about');
        $this->addSql('DROP INDEX idx_page_about_language_id ON page_about');
        $this->addSql('ALTER TABLE page_about DROP language_id, ADD language VARCHAR(2) NOT NULL COMMENT "(DC2Type:EnumLanguage)"');

        $this->addSql('ALTER TABLE page_contact DROP FOREIGN KEY FK_C8EE6D4782F1BAF4');
        $this->addSql('DROP INDEX uq_page_contact_language_id ON page_contact');
        $this->addSql('DROP INDEX idx_page_contact_language_id ON page_contact');
        $this->addSql('ALTER TABLE page_contact DROP language_id, ADD language VARCHAR(2) NOT NULL COMMENT "(DC2Type:EnumLanguage)"');

        $this->addSql('ALTER TABLE page_faq DROP FOREIGN KEY FK_C743A75C82F1BAF4');
        $this->addSql('DROP INDEX uq_page_faq_language_id ON page_faq');
        $this->addSql('DROP INDEX idx_page_faq_language_id ON page_faq');
        $this->addSql('ALTER TABLE page_faq DROP language_id, ADD language VARCHAR(2) NOT NULL COMMENT "(DC2Type:EnumLanguage)"');

        $this->addSql('DROP TABLE page_language');
    }
}
