<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250205120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow multiple resolved duels per pair by introducing a nullable pending flag and adjusting unique indexes.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('game4_duel')) {
            return;
        }

        $table = $schema->getTable('game4_duel');
        $indexNames = [
            'uniq_g4duel_pair_pending',
            'uniq_duel_playerA_pending',
            'uniq_duel_playerB_pending',
        ];

        foreach ($indexNames as $indexName) {
            $normalized = strtoupper($indexName);
            if ($table->hasIndex($indexName) || $table->hasIndex($normalized)) {
                $this->addSql(sprintf('ALTER TABLE game4_duel DROP INDEX %s', $indexName));
            }
        }

        if (!$table->hasColumn('pending_flag')) {
            $this->addSql("ALTER TABLE game4_duel ADD pending_flag TINYINT(1) DEFAULT 1");
        }

        $this->addSql("UPDATE game4_duel SET pending_flag = CASE WHEN status = 'PENDING' THEN 1 ELSE NULL END");
        $this->addSql('CREATE UNIQUE INDEX uniq_g4duel_pair_pending ON game4_duel (game_id, player_a_id, player_b_id, pending_flag)');
        $this->addSql('CREATE UNIQUE INDEX uniq_duel_playerA_pending ON game4_duel (game_id, player_a_id, pending_flag)');
        $this->addSql('CREATE UNIQUE INDEX uniq_duel_playerB_pending ON game4_duel (game_id, player_b_id, pending_flag)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_g4duel_pair_pending ON game4_duel');
        $this->addSql('DROP INDEX uniq_duel_playerA_pending ON game4_duel');
        $this->addSql('DROP INDEX uniq_duel_playerB_pending ON game4_duel');
        $this->addSql('ALTER TABLE game4_duel DROP COLUMN pending_flag');
        $this->addSql('CREATE UNIQUE INDEX uniq_g4duel_pair_pending ON game4_duel (game_id, player_a_id, player_b_id, status)');
        $this->addSql('CREATE UNIQUE INDEX uniq_duel_playerA_pending ON game4_duel (game_id, player_a_id, status)');
        $this->addSql('CREATE UNIQUE INDEX uniq_duel_playerB_pending ON game4_duel (game_id, player_b_id, status)');
    }
}
