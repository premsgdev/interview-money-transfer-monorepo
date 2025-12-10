<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251210172613 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE account (id INT AUTO_INCREMENT NOT NULL, account_uuid VARCHAR(36) NOT NULL, balance NUMERIC(18, 2) NOT NULL, currency VARCHAR(3) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_uuid VARCHAR(36) NOT NULL, UNIQUE INDEX UNIQ_7D3656A45DECD70C (account_uuid), INDEX IDX_7D3656A4ABFE1C6F (user_uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE transfer (id INT AUTO_INCREMENT NOT NULL, transfer_uuid VARCHAR(36) NOT NULL, amount NUMERIC(18, 2) NOT NULL, currency VARCHAR(3) NOT NULL, created_at DATETIME NOT NULL, from_account_id INT NOT NULL, to_account_id INT NOT NULL, initiator_user_uuid VARCHAR(36) NOT NULL, UNIQUE INDEX UNIQ_4034A3C0E98A7CC4 (transfer_uuid), INDEX IDX_4034A3C0B0CF99BD (from_account_id), INDEX IDX_4034A3C0BC58BDC7 (to_account_id), INDEX IDX_4034A3C02E96DF94 (initiator_user_uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE account ADD CONSTRAINT FK_7D3656A4ABFE1C6F FOREIGN KEY (user_uuid) REFERENCES user (uuid)');
        $this->addSql('ALTER TABLE transfer ADD CONSTRAINT FK_4034A3C0B0CF99BD FOREIGN KEY (from_account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE transfer ADD CONSTRAINT FK_4034A3C0BC58BDC7 FOREIGN KEY (to_account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE transfer ADD CONSTRAINT FK_4034A3C02E96DF94 FOREIGN KEY (initiator_user_uuid) REFERENCES user (uuid)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE account DROP FOREIGN KEY FK_7D3656A4ABFE1C6F');
        $this->addSql('ALTER TABLE transfer DROP FOREIGN KEY FK_4034A3C0B0CF99BD');
        $this->addSql('ALTER TABLE transfer DROP FOREIGN KEY FK_4034A3C0BC58BDC7');
        $this->addSql('ALTER TABLE transfer DROP FOREIGN KEY FK_4034A3C02E96DF94');
        $this->addSql('DROP TABLE account');
        $this->addSql('DROP TABLE transfer');
    }
}
