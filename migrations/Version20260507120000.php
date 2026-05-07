<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260507120000 extends AbstractMigration {
public function getDescription(): string { return 'Add template, cover page, cover letter and link template to resume'; }
public function up(Schema $schema): void {
$this->addSql("CREATE TABLE recruit_template (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', name VARCHAR(255) NOT NULL, type VARCHAR(30) NOT NULL, version INT NOT NULL, layout VARCHAR(100) DEFAULT NULL, structure_name VARCHAR(100) DEFAULT NULL, sections JSON DEFAULT NULL, theme JSON DEFAULT NULL, aside_data JSON DEFAULT NULL, photo JSON DEFAULT NULL, decor JSON DEFAULT NULL, layout_options JSON DEFAULT NULL, decor_options JSON DEFAULT NULL, section_title_style JSON DEFAULT NULL, header_type VARCHAR(100) DEFAULT NULL, fake_data JSON DEFAULT NULL, text_styles JSON DEFAULT NULL, typography JSON DEFAULT NULL, section_bar JSON DEFAULT NULL, items JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
$this->addSql("CREATE TABLE recruit_cover_page (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', template_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', full_name VARCHAR(255) DEFAULT NULL, role_name VARCHAR(255) DEFAULT NULL, photo VARCHAR(1024) DEFAULT NULL, description LONGTEXT DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, header VARCHAR(255) DEFAULT NULL, profile LONGTEXT DEFAULT NULL, signature VARCHAR(1024) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_7E02A15A5DA0FB8 (template_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
$this->addSql("CREATE TABLE recruit_cover_letter (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', template_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', full_name VARCHAR(255) DEFAULT NULL, role_name VARCHAR(255) DEFAULT NULL, photo VARCHAR(1024) DEFAULT NULL, sender_date DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)', location VARCHAR(255) DEFAULT NULL, header VARCHAR(255) DEFAULT NULL, description_1 LONGTEXT DEFAULT NULL, description_2 LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_D867F1A85DA0FB8 (template_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
$this->addSql('ALTER TABLE recruit_cover_page ADD CONSTRAINT FK_7E02A15A5DA0FB8 FOREIGN KEY (template_id) REFERENCES recruit_template (id) ON DELETE SET NULL');
$this->addSql('ALTER TABLE recruit_cover_letter ADD CONSTRAINT FK_D867F1A85DA0FB8 FOREIGN KEY (template_id) REFERENCES recruit_template (id) ON DELETE SET NULL');
$this->addSql("ALTER TABLE recruit_resume ADD template_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid_binary_ordered_time)', ADD CONSTRAINT FK_B8B20E35DA0FB8 FOREIGN KEY (template_id) REFERENCES recruit_template (id) ON DELETE SET NULL");
$this->addSql('CREATE INDEX IDX_B8B20E35DA0FB8 ON recruit_resume (template_id)'); }
public function down(Schema $schema): void {
$this->addSql('ALTER TABLE recruit_resume DROP FOREIGN KEY FK_B8B20E35DA0FB8');
$this->addSql('DROP INDEX IDX_B8B20E35DA0FB8 ON recruit_resume');
$this->addSql('ALTER TABLE recruit_resume DROP template_id');
$this->addSql('ALTER TABLE recruit_cover_page DROP FOREIGN KEY FK_7E02A15A5DA0FB8');
$this->addSql('ALTER TABLE recruit_cover_letter DROP FOREIGN KEY FK_D867F1A85DA0FB8');
$this->addSql('DROP TABLE recruit_cover_page'); $this->addSql('DROP TABLE recruit_cover_letter'); $this->addSql('DROP TABLE recruit_template'); }}
