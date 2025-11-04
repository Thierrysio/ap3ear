<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251103143051 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE game4_card (id INT AUTO_INCREMENT NOT NULL, game_id INT NOT NULL, def_id INT NOT NULL, owner_id INT DEFAULT NULL, zone VARCHAR(16) NOT NULL, token VARCHAR(128) NOT NULL, INDEX IDX_1B316EDEE48FD905 (game_id), INDEX IDX_1B316EDEF438A02F (def_id), INDEX IDX_1B316EDE7E3C61F9 (owner_id), INDEX idx_game_zone (game_id, zone), UNIQUE INDEX uniq_token (token), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE game4_card_def (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(32) NOT NULL, label VARCHAR(64) NOT NULL, text LONGTEXT DEFAULT NULL, type VARCHAR(16) NOT NULL, UNIQUE INDEX uniq_g4carddef_code (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE game4_duel (id INT AUTO_INCREMENT NOT NULL, game_id INT NOT NULL, player_a_id INT NOT NULL, player_b_id INT NOT NULL, winner_id INT DEFAULT NULL, status VARCHAR(16) NOT NULL, round_index INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', resolved_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', logs LONGTEXT DEFAULT NULL, INDEX IDX_9691516FE48FD905 (game_id), INDEX IDX_9691516F99C4036B (player_a_id), INDEX IDX_9691516F8B71AC85 (player_b_id), INDEX IDX_9691516F5DFCD4B8 (winner_id), INDEX idx_g4duel_game_status (game_id, status), INDEX idx_g4duel_game_playerA (game_id, player_a_id), INDEX idx_g4duel_game_playerB (game_id, player_b_id), UNIQUE INDEX uniq_g4duel_pair_pending (game_id, player_a_id, player_b_id, status), UNIQUE INDEX uniq_duel_playerA_pending (game_id, player_a_id, status), UNIQUE INDEX uniq_duel_playerB_pending (game_id, player_b_id, status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE game4_duel_play (id INT AUTO_INCREMENT NOT NULL, duel_id INT NOT NULL, player_id INT NOT NULL, card_id INT DEFAULT NULL, card_code VARCHAR(64) NOT NULL, card_type VARCHAR(16) NOT NULL, num_value INT DEFAULT NULL, round_index INT NOT NULL, submitted_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_B0B5D9A199E6F5DF (player_id), INDEX IDX_B0B5D9A14ACC9A20 (card_id), INDEX idx_duel (duel_id), INDEX idx_duel_player (duel_id, player_id), INDEX idx_duel_round (duel_id, round_index), INDEX idx_duel_type (duel_id, card_type), UNIQUE INDEX uniq_duel_player_round (duel_id, player_id, round_index), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE game4_game (id INT AUTO_INCREMENT NOT NULL, phase VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ends_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', draw_count INT DEFAULT 0 NOT NULL, discard_count INT DEFAULT 0 NOT NULL, round_index INT DEFAULT 0 NOT NULL, rounds_max INT DEFAULT 20 NOT NULL, round_seconds INT DEFAULT 60 NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE game4_player (id INT AUTO_INCREMENT NOT NULL, game_id INT NOT NULL, incoming_duel_id INT DEFAULT NULL, equipe_id INT NOT NULL, name VARCHAR(100) NOT NULL, role VARCHAR(12) NOT NULL, lives INT DEFAULT 3 NOT NULL, is_alive TINYINT(1) DEFAULT 1 NOT NULL, locked_in_duel TINYINT(1) DEFAULT 0 NOT NULL, is_zombie TINYINT(1) DEFAULT 0 NOT NULL, is_eliminated TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_79695E24E48FD905 (game_id), INDEX IDX_79695E2458994BC (incoming_duel_id), INDEX idx_g4player_game_alive (game_id, is_alive), INDEX idx_g4player_game_role (game_id, role), INDEX idx_g4player_game_locked (game_id, locked_in_duel), UNIQUE INDEX uniq_game_equipe (game_id, equipe_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE set_choix_bonto_dto (id INT AUTO_INCREMENT NOT NULL, manche INT NOT NULL, equipe_id INT NOT NULL, choix_index INT NOT NULL, gagnant VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tcompetition (id INT AUTO_INCREMENT NOT NULL, date_debut DATETIME NOT NULL, datefin DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tepreuve (id INT AUTO_INCREMENT NOT NULL, la_competition_id INT DEFAULT NULL, nom VARCHAR(255) NOT NULL, datedebut DATETIME NOT NULL, dureemax INT NOT NULL, INDEX IDX_6F97B02C8A85F8E (la_competition_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tepreuve3_finish (id INT AUTO_INCREMENT NOT NULL, equipe_id INT NOT NULL, epreuve_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tepreuve3_next (id INT AUTO_INCREMENT NOT NULL, lat DOUBLE PRECISION NOT NULL, lon DOUBLE PRECISION NOT NULL, hint VARCHAR(255) NOT NULL, time_out_sec INT NOT NULL, valid TINYINT(1) NOT NULL, scanned_token_hash VARCHAR(255) NOT NULL, flag_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tequipe (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tflag (id INT AUTO_INCREMENT NOT NULL, epreuve_id INT DEFAULT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, indication VARCHAR(255) NOT NULL, timeout_sec INT NOT NULL, ordre INT NOT NULL, INDEX IDX_3AC08594AB990336 (epreuve_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tscore (id INT AUTO_INCREMENT NOT NULL, lat_epreuve_id INT DEFAULT NULL, lat_equipe_id INT DEFAULT NULL, note INT NOT NULL, INDEX IDX_D5CA2E3820EF9C31 (lat_epreuve_id), INDEX IDX_D5CA2E38A5A1F6D3 (lat_equipe_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, lat_equipe_id INT DEFAULT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, statut TINYINT(1) NOT NULL, INDEX IDX_8D93D649A5A1F6D3 (lat_equipe_id), UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE game4_card ADD CONSTRAINT FK_1B316EDEE48FD905 FOREIGN KEY (game_id) REFERENCES game4_game (id)');
        $this->addSql('ALTER TABLE game4_card ADD CONSTRAINT FK_1B316EDEF438A02F FOREIGN KEY (def_id) REFERENCES game4_card_def (id)');
        $this->addSql('ALTER TABLE game4_card ADD CONSTRAINT FK_1B316EDE7E3C61F9 FOREIGN KEY (owner_id) REFERENCES game4_player (id)');
        $this->addSql('ALTER TABLE game4_duel ADD CONSTRAINT FK_9691516FE48FD905 FOREIGN KEY (game_id) REFERENCES game4_game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE game4_duel ADD CONSTRAINT FK_9691516F99C4036B FOREIGN KEY (player_a_id) REFERENCES game4_player (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE game4_duel ADD CONSTRAINT FK_9691516F8B71AC85 FOREIGN KEY (player_b_id) REFERENCES game4_player (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE game4_duel ADD CONSTRAINT FK_9691516F5DFCD4B8 FOREIGN KEY (winner_id) REFERENCES game4_player (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE game4_duel_play ADD CONSTRAINT FK_B0B5D9A158875E FOREIGN KEY (duel_id) REFERENCES game4_duel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE game4_duel_play ADD CONSTRAINT FK_B0B5D9A199E6F5DF FOREIGN KEY (player_id) REFERENCES game4_player (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE game4_duel_play ADD CONSTRAINT FK_B0B5D9A14ACC9A20 FOREIGN KEY (card_id) REFERENCES game4_card (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE game4_player ADD CONSTRAINT FK_79695E24E48FD905 FOREIGN KEY (game_id) REFERENCES game4_game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE game4_player ADD CONSTRAINT FK_79695E2458994BC FOREIGN KEY (incoming_duel_id) REFERENCES game4_duel (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE tepreuve ADD CONSTRAINT FK_6F97B02C8A85F8E FOREIGN KEY (la_competition_id) REFERENCES tcompetition (id)');
        $this->addSql('ALTER TABLE tflag ADD CONSTRAINT FK_3AC08594AB990336 FOREIGN KEY (epreuve_id) REFERENCES tepreuve (id)');
        $this->addSql('ALTER TABLE tscore ADD CONSTRAINT FK_D5CA2E3820EF9C31 FOREIGN KEY (lat_epreuve_id) REFERENCES tepreuve (id)');
        $this->addSql('ALTER TABLE tscore ADD CONSTRAINT FK_D5CA2E38A5A1F6D3 FOREIGN KEY (lat_equipe_id) REFERENCES tequipe (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649A5A1F6D3 FOREIGN KEY (lat_equipe_id) REFERENCES tequipe (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE game4_card DROP FOREIGN KEY FK_1B316EDEE48FD905');
        $this->addSql('ALTER TABLE game4_card DROP FOREIGN KEY FK_1B316EDEF438A02F');
        $this->addSql('ALTER TABLE game4_card DROP FOREIGN KEY FK_1B316EDE7E3C61F9');
        $this->addSql('ALTER TABLE game4_duel DROP FOREIGN KEY FK_9691516FE48FD905');
        $this->addSql('ALTER TABLE game4_duel DROP FOREIGN KEY FK_9691516F99C4036B');
        $this->addSql('ALTER TABLE game4_duel DROP FOREIGN KEY FK_9691516F8B71AC85');
        $this->addSql('ALTER TABLE game4_duel DROP FOREIGN KEY FK_9691516F5DFCD4B8');
        $this->addSql('ALTER TABLE game4_duel_play DROP FOREIGN KEY FK_B0B5D9A158875E');
        $this->addSql('ALTER TABLE game4_duel_play DROP FOREIGN KEY FK_B0B5D9A199E6F5DF');
        $this->addSql('ALTER TABLE game4_duel_play DROP FOREIGN KEY FK_B0B5D9A14ACC9A20');
        $this->addSql('ALTER TABLE game4_player DROP FOREIGN KEY FK_79695E24E48FD905');
        $this->addSql('ALTER TABLE game4_player DROP FOREIGN KEY FK_79695E2458994BC');
        $this->addSql('ALTER TABLE tepreuve DROP FOREIGN KEY FK_6F97B02C8A85F8E');
        $this->addSql('ALTER TABLE tflag DROP FOREIGN KEY FK_3AC08594AB990336');
        $this->addSql('ALTER TABLE tscore DROP FOREIGN KEY FK_D5CA2E3820EF9C31');
        $this->addSql('ALTER TABLE tscore DROP FOREIGN KEY FK_D5CA2E38A5A1F6D3');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649A5A1F6D3');
        $this->addSql('DROP TABLE game4_card');
        $this->addSql('DROP TABLE game4_card_def');
        $this->addSql('DROP TABLE game4_duel');
        $this->addSql('DROP TABLE game4_duel_play');
        $this->addSql('DROP TABLE game4_game');
        $this->addSql('DROP TABLE game4_player');
        $this->addSql('DROP TABLE set_choix_bonto_dto');
        $this->addSql('DROP TABLE tcompetition');
        $this->addSql('DROP TABLE tepreuve');
        $this->addSql('DROP TABLE tepreuve3_finish');
        $this->addSql('DROP TABLE tepreuve3_next');
        $this->addSql('DROP TABLE tequipe');
        $this->addSql('DROP TABLE tflag');
        $this->addSql('DROP TABLE tscore');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
