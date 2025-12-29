<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251228162056 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE applied_effect (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', game_session_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', round_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', team_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', source_type VARCHAR(20) NOT NULL, effect_type VARCHAR(32) NOT NULL, payload JSON NOT NULL, applied_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', scheduled_round_index INT DEFAULT NULL, INDEX IDX_B98102088FE32B32 (game_session_id), INDEX IDX_B9810208A6005CA0 (round_id), INDEX IDX_B9810208296CD8AE (team_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE aurelien_lieu (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, image_url VARCHAR(255) NOT NULL, code_qr VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE aurelien_question (id INT AUTO_INCREMENT NOT NULL, lieu_id INT NOT NULL, libelle VARCHAR(255) NOT NULL, INDEX IDX_8F16AA836AB213CC (lieu_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE aurelien_reponse (id INT AUTO_INCREMENT NOT NULL, question_id INT NOT NULL, libelle VARCHAR(255) NOT NULL, est_correcte TINYINT(1) NOT NULL, INDEX IDX_BDE6D1241E27F6BF (question_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE choice (id INT AUTO_INCREMENT NOT NULL, question_id INT NOT NULL, texte VARCHAR(255) NOT NULL, is_correct TINYINT(1) NOT NULL, INDEX IDX_C1AB5A921E27F6BF (question_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE future_option (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', round_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', code VARCHAR(10) NOT NULL, title VARCHAR(255) NOT NULL, risk_level VARCHAR(16) NOT NULL, immediate_effects JSON NOT NULL, delayed_effects JSON NOT NULL, display_order INT NOT NULL, INDEX IDX_3B97A789A6005CA0 (round_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE future_pick (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', round_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', team_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', future_option_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', picked_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', pick_mode VARCHAR(20) NOT NULL, locked TINYINT(1) NOT NULL, INDEX IDX_AB7D7910A6005CA0 (round_id), INDEX IDX_AB7D7910296CD8AE (team_id), INDEX IDX_AB7D7910788C9CD (future_option_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE game4_card (id INT AUTO_INCREMENT NOT NULL, game_id INT NOT NULL, def_id INT NOT NULL, owner_id INT DEFAULT NULL, zone VARCHAR(16) NOT NULL, token VARCHAR(128) NOT NULL, INDEX IDX_1B316EDEE48FD905 (game_id), INDEX IDX_1B316EDEF438A02F (def_id), INDEX IDX_1B316EDE7E3C61F9 (owner_id), INDEX idx_game_zone (game_id, zone), UNIQUE INDEX uniq_token (token), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE game4_card_def (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(32) NOT NULL, label VARCHAR(64) NOT NULL, text LONGTEXT DEFAULT NULL, type VARCHAR(16) NOT NULL, UNIQUE INDEX uniq_g4carddef_code (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE game4_duel (id INT AUTO_INCREMENT NOT NULL, game_id INT NOT NULL, player_a_id INT NOT NULL, player_b_id INT NOT NULL, winner_id INT DEFAULT NULL, status VARCHAR(16) NOT NULL, pending_flag TINYINT(1) DEFAULT 1, round_index INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', resolved_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', logs LONGTEXT DEFAULT NULL, INDEX IDX_9691516FE48FD905 (game_id), INDEX IDX_9691516F99C4036B (player_a_id), INDEX IDX_9691516F8B71AC85 (player_b_id), INDEX IDX_9691516F5DFCD4B8 (winner_id), INDEX idx_g4duel_game_status (game_id, status), INDEX idx_g4duel_game_playerA (game_id, player_a_id), INDEX idx_g4duel_game_playerB (game_id, player_b_id), UNIQUE INDEX uniq_g4duel_pair_pending (game_id, player_a_id, player_b_id, pending_flag), UNIQUE INDEX uniq_duel_playerA_pending (game_id, player_a_id, pending_flag), UNIQUE INDEX uniq_duel_playerB_pending (game_id, player_b_id, pending_flag), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE game4_duel_play (id INT AUTO_INCREMENT NOT NULL, duel_id INT NOT NULL, player_id INT NOT NULL, card_id INT DEFAULT NULL, card_code VARCHAR(64) NOT NULL, card_type VARCHAR(16) NOT NULL, num_value INT DEFAULT NULL, round_index INT NOT NULL, submitted_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_B0B5D9A199E6F5DF (player_id), INDEX IDX_B0B5D9A14ACC9A20 (card_id), INDEX idx_duel (duel_id), INDEX idx_duel_player (duel_id, player_id), INDEX idx_duel_round (duel_id, round_index), INDEX idx_duel_type (duel_id, card_type), UNIQUE INDEX uniq_duel_player_round (duel_id, player_id, round_index), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE game4_game (id INT AUTO_INCREMENT NOT NULL, phase VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ends_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', draw_count INT DEFAULT 0 NOT NULL, discard_count INT DEFAULT 0 NOT NULL, round_index INT DEFAULT 0 NOT NULL, rounds_max INT DEFAULT 20 NOT NULL, round_seconds INT DEFAULT 60 NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE game4_player (id INT AUTO_INCREMENT NOT NULL, game_id INT NOT NULL, incoming_duel_id INT DEFAULT NULL, equipe_id INT NOT NULL, name VARCHAR(100) NOT NULL, role VARCHAR(12) NOT NULL, lives INT DEFAULT 3 NOT NULL, is_alive TINYINT(1) DEFAULT 1 NOT NULL, locked_in_duel TINYINT(1) DEFAULT 0 NOT NULL, is_zombie TINYINT(1) DEFAULT 0 NOT NULL, is_eliminated TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_79695E24E48FD905 (game_id), INDEX IDX_79695E2458994BC (incoming_duel_id), INDEX idx_g4player_game_alive (game_id, is_alive), INDEX idx_g4player_game_role (game_id, role), INDEX idx_g4player_game_locked (game_id, locked_in_duel), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE game_session (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', name VARCHAR(255) NOT NULL, game_code VARCHAR(32) NOT NULL, status VARCHAR(16) NOT NULL, max_teams INT DEFAULT 10 NOT NULL, ev_start INT DEFAULT 15 NOT NULL, choice_timeout_sec INT DEFAULT 15 NOT NULL, gps_enabled TINYINT(1) NOT NULL, gps_radius_meters INT DEFAULT NULL, current_round_index INT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', started_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', finished_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', settings JSON NOT NULL, UNIQUE INDEX UNIQ_4586AAFB9C005CB4 (game_code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE nohad_question (id INT AUTO_INCREMENT NOT NULL, enonce VARCHAR(255) NOT NULL, duree_secondes INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE nohad_reponse (id INT AUTO_INCREMENT NOT NULL, question_id INT NOT NULL, identifiant VARCHAR(255) NOT NULL, texte VARCHAR(255) NOT NULL, INDEX IDX_3AC64B5D1E27F6BF (question_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE qr_scan (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', round_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', team_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', scanned_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', client_timestamp DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', gps_lat DOUBLE PRECISION DEFAULT NULL, gps_lng DOUBLE PRECISION DEFAULT NULL, gps_accuracy DOUBLE PRECISION DEFAULT NULL, scan_status VARCHAR(20) NOT NULL, `rank` INT DEFAULT NULL, rejection_reason VARCHAR(255) DEFAULT NULL, INDEX IDX_CE2D9C83A6005CA0 (round_id), INDEX IDX_CE2D9C83296CD8AE (team_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE question (id INT AUTO_INCREMENT NOT NULL, quiz_id INT NOT NULL, enonce VARCHAR(255) NOT NULL, duree_secondes INT NOT NULL, INDEX IDX_B6F7494E853CD175 (quiz_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE quiz (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE rounds (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', game_session_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', round_index INT NOT NULL, title VARCHAR(255) NOT NULL, physical_challenge JSON NOT NULL, qr_payload_expected VARCHAR(255) NOT NULL, time_limit_sec INT NOT NULL, status VARCHAR(16) NOT NULL, start_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', end_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_3A7FD5548FE32B32 (game_session_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE score (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, points INT NOT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_32993751A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE set_choix_bonto_dto (id INT AUTO_INCREMENT NOT NULL, manche INT NOT NULL, equipe_id INT NOT NULL, choix_index INT NOT NULL, gagnant VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tcompetition (id INT AUTO_INCREMENT NOT NULL, date_debut DATETIME NOT NULL, datefin DATETIME NOT NULL, nom VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tcompetition_tequipe (tcompetition_id INT NOT NULL, tequipe_id INT NOT NULL, INDEX IDX_5B8712DDF17116FD (tcompetition_id), INDEX IDX_5B8712DD97A742B3 (tequipe_id), PRIMARY KEY(tcompetition_id, tequipe_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE team (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', game_session_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', name VARCHAR(255) NOT NULL, members_count INT NOT NULL, ev_current INT NOT NULL, status VARCHAR(16) NOT NULL, device_id VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_C4E0A61F8FE32B32 (game_session_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tepreuve (id INT AUTO_INCREMENT NOT NULL, la_competition_id INT DEFAULT NULL, nom VARCHAR(255) NOT NULL, datedebut DATETIME NOT NULL, dureemax INT NOT NULL, INDEX IDX_6F97B02C8A85F8E (la_competition_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tepreuve3_finish (id INT AUTO_INCREMENT NOT NULL, equipe_id INT NOT NULL, epreuve_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tepreuve3_next (id INT AUTO_INCREMENT NOT NULL, lat DOUBLE PRECISION NOT NULL, lon DOUBLE PRECISION NOT NULL, hint VARCHAR(255) NOT NULL, time_out_sec INT NOT NULL, valid TINYINT(1) NOT NULL, scanned_token_hash VARCHAR(255) NOT NULL, flag_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tequipe (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, _score INT DEFAULT 0 NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tflag (id INT AUTO_INCREMENT NOT NULL, epreuve_id INT DEFAULT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, indication VARCHAR(255) NOT NULL, timeout_sec INT NOT NULL, ordre INT NOT NULL, INDEX IDX_3AC08594AB990336 (epreuve_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tscore (id INT AUTO_INCREMENT NOT NULL, lat_epreuve_id INT DEFAULT NULL, lat_equipe_id INT DEFAULT NULL, note INT NOT NULL, INDEX IDX_D5CA2E3820EF9C31 (lat_epreuve_id), INDEX IDX_D5CA2E38A5A1F6D3 (lat_equipe_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, lat_equipe_id INT DEFAULT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, statut TINYINT(1) NOT NULL, INDEX IDX_8D93D649A5A1F6D3 (lat_equipe_id), UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_response (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, question_id INT NOT NULL, choice_id INT NOT NULL, response_time_ms INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_DEF6EFFBA76ED395 (user_id), INDEX IDX_DEF6EFFB1E27F6BF (question_id), INDEX IDX_DEF6EFFB998666D1 (choice_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE applied_effect ADD CONSTRAINT FK_B98102088FE32B32 FOREIGN KEY (game_session_id) REFERENCES game_session (id)');
        $this->addSql('ALTER TABLE applied_effect ADD CONSTRAINT FK_B9810208A6005CA0 FOREIGN KEY (round_id) REFERENCES rounds (id)');
        $this->addSql('ALTER TABLE applied_effect ADD CONSTRAINT FK_B9810208296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE aurelien_question ADD CONSTRAINT FK_8F16AA836AB213CC FOREIGN KEY (lieu_id) REFERENCES aurelien_lieu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE aurelien_reponse ADD CONSTRAINT FK_BDE6D1241E27F6BF FOREIGN KEY (question_id) REFERENCES aurelien_question (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE choice ADD CONSTRAINT FK_C1AB5A921E27F6BF FOREIGN KEY (question_id) REFERENCES question (id)');
        $this->addSql('ALTER TABLE future_option ADD CONSTRAINT FK_3B97A789A6005CA0 FOREIGN KEY (round_id) REFERENCES rounds (id)');
        $this->addSql('ALTER TABLE future_pick ADD CONSTRAINT FK_AB7D7910A6005CA0 FOREIGN KEY (round_id) REFERENCES rounds (id)');
        $this->addSql('ALTER TABLE future_pick ADD CONSTRAINT FK_AB7D7910296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE future_pick ADD CONSTRAINT FK_AB7D7910788C9CD FOREIGN KEY (future_option_id) REFERENCES future_option (id)');
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
        $this->addSql('ALTER TABLE nohad_reponse ADD CONSTRAINT FK_3AC64B5D1E27F6BF FOREIGN KEY (question_id) REFERENCES nohad_question (id)');
        $this->addSql('ALTER TABLE qr_scan ADD CONSTRAINT FK_CE2D9C83A6005CA0 FOREIGN KEY (round_id) REFERENCES rounds (id)');
        $this->addSql('ALTER TABLE qr_scan ADD CONSTRAINT FK_CE2D9C83296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494E853CD175 FOREIGN KEY (quiz_id) REFERENCES quiz (id)');
        $this->addSql('ALTER TABLE rounds ADD CONSTRAINT FK_3A7FD5548FE32B32 FOREIGN KEY (game_session_id) REFERENCES game_session (id)');
        $this->addSql('ALTER TABLE score ADD CONSTRAINT FK_32993751A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE tcompetition_tequipe ADD CONSTRAINT FK_5B8712DDF17116FD FOREIGN KEY (tcompetition_id) REFERENCES tcompetition (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tcompetition_tequipe ADD CONSTRAINT FK_5B8712DD97A742B3 FOREIGN KEY (tequipe_id) REFERENCES tequipe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE team ADD CONSTRAINT FK_C4E0A61F8FE32B32 FOREIGN KEY (game_session_id) REFERENCES game_session (id)');
        $this->addSql('ALTER TABLE tepreuve ADD CONSTRAINT FK_6F97B02C8A85F8E FOREIGN KEY (la_competition_id) REFERENCES tcompetition (id)');
        $this->addSql('ALTER TABLE tflag ADD CONSTRAINT FK_3AC08594AB990336 FOREIGN KEY (epreuve_id) REFERENCES tepreuve (id)');
        $this->addSql('ALTER TABLE tscore ADD CONSTRAINT FK_D5CA2E3820EF9C31 FOREIGN KEY (lat_epreuve_id) REFERENCES tepreuve (id)');
        $this->addSql('ALTER TABLE tscore ADD CONSTRAINT FK_D5CA2E38A5A1F6D3 FOREIGN KEY (lat_equipe_id) REFERENCES tequipe (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649A5A1F6D3 FOREIGN KEY (lat_equipe_id) REFERENCES tequipe (id)');
        $this->addSql('ALTER TABLE user_response ADD CONSTRAINT FK_DEF6EFFBA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_response ADD CONSTRAINT FK_DEF6EFFB1E27F6BF FOREIGN KEY (question_id) REFERENCES question (id)');
        $this->addSql('ALTER TABLE user_response ADD CONSTRAINT FK_DEF6EFFB998666D1 FOREIGN KEY (choice_id) REFERENCES choice (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE applied_effect DROP FOREIGN KEY FK_B98102088FE32B32');
        $this->addSql('ALTER TABLE applied_effect DROP FOREIGN KEY FK_B9810208A6005CA0');
        $this->addSql('ALTER TABLE applied_effect DROP FOREIGN KEY FK_B9810208296CD8AE');
        $this->addSql('ALTER TABLE aurelien_question DROP FOREIGN KEY FK_8F16AA836AB213CC');
        $this->addSql('ALTER TABLE aurelien_reponse DROP FOREIGN KEY FK_BDE6D1241E27F6BF');
        $this->addSql('ALTER TABLE choice DROP FOREIGN KEY FK_C1AB5A921E27F6BF');
        $this->addSql('ALTER TABLE future_option DROP FOREIGN KEY FK_3B97A789A6005CA0');
        $this->addSql('ALTER TABLE future_pick DROP FOREIGN KEY FK_AB7D7910A6005CA0');
        $this->addSql('ALTER TABLE future_pick DROP FOREIGN KEY FK_AB7D7910296CD8AE');
        $this->addSql('ALTER TABLE future_pick DROP FOREIGN KEY FK_AB7D7910788C9CD');
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
        $this->addSql('ALTER TABLE nohad_reponse DROP FOREIGN KEY FK_3AC64B5D1E27F6BF');
        $this->addSql('ALTER TABLE qr_scan DROP FOREIGN KEY FK_CE2D9C83A6005CA0');
        $this->addSql('ALTER TABLE qr_scan DROP FOREIGN KEY FK_CE2D9C83296CD8AE');
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494E853CD175');
        $this->addSql('ALTER TABLE rounds DROP FOREIGN KEY FK_3A7FD5548FE32B32');
        $this->addSql('ALTER TABLE score DROP FOREIGN KEY FK_32993751A76ED395');
        $this->addSql('ALTER TABLE tcompetition_tequipe DROP FOREIGN KEY FK_5B8712DDF17116FD');
        $this->addSql('ALTER TABLE tcompetition_tequipe DROP FOREIGN KEY FK_5B8712DD97A742B3');
        $this->addSql('ALTER TABLE team DROP FOREIGN KEY FK_C4E0A61F8FE32B32');
        $this->addSql('ALTER TABLE tepreuve DROP FOREIGN KEY FK_6F97B02C8A85F8E');
        $this->addSql('ALTER TABLE tflag DROP FOREIGN KEY FK_3AC08594AB990336');
        $this->addSql('ALTER TABLE tscore DROP FOREIGN KEY FK_D5CA2E3820EF9C31');
        $this->addSql('ALTER TABLE tscore DROP FOREIGN KEY FK_D5CA2E38A5A1F6D3');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649A5A1F6D3');
        $this->addSql('ALTER TABLE user_response DROP FOREIGN KEY FK_DEF6EFFBA76ED395');
        $this->addSql('ALTER TABLE user_response DROP FOREIGN KEY FK_DEF6EFFB1E27F6BF');
        $this->addSql('ALTER TABLE user_response DROP FOREIGN KEY FK_DEF6EFFB998666D1');
        $this->addSql('DROP TABLE applied_effect');
        $this->addSql('DROP TABLE aurelien_lieu');
        $this->addSql('DROP TABLE aurelien_question');
        $this->addSql('DROP TABLE aurelien_reponse');
        $this->addSql('DROP TABLE choice');
        $this->addSql('DROP TABLE future_option');
        $this->addSql('DROP TABLE future_pick');
        $this->addSql('DROP TABLE game4_card');
        $this->addSql('DROP TABLE game4_card_def');
        $this->addSql('DROP TABLE game4_duel');
        $this->addSql('DROP TABLE game4_duel_play');
        $this->addSql('DROP TABLE game4_game');
        $this->addSql('DROP TABLE game4_player');
        $this->addSql('DROP TABLE game_session');
        $this->addSql('DROP TABLE nohad_question');
        $this->addSql('DROP TABLE nohad_reponse');
        $this->addSql('DROP TABLE qr_scan');
        $this->addSql('DROP TABLE question');
        $this->addSql('DROP TABLE quiz');
        $this->addSql('DROP TABLE rounds');
        $this->addSql('DROP TABLE score');
        $this->addSql('DROP TABLE set_choix_bonto_dto');
        $this->addSql('DROP TABLE tcompetition');
        $this->addSql('DROP TABLE tcompetition_tequipe');
        $this->addSql('DROP TABLE team');
        $this->addSql('DROP TABLE tepreuve');
        $this->addSql('DROP TABLE tepreuve3_finish');
        $this->addSql('DROP TABLE tepreuve3_next');
        $this->addSql('DROP TABLE tequipe');
        $this->addSql('DROP TABLE tflag');
        $this->addSql('DROP TABLE tscore');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_response');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
