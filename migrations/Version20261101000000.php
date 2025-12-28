<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add game session domain tables for rounds, teams, scans and effects';
    }

    public function up(Schema $schema): void
    {
        $gameSession = $schema->createTable('game_session');
        $gameSession->addColumn('id', 'guid');
        $gameSession->addColumn('name', 'string', ['length' => 255]);
        $gameSession->addColumn('game_code', 'string', ['length' => 32]);
        $gameSession->addColumn('status', 'string', ['length' => 16]);
        $gameSession->addColumn('max_teams', 'integer', ['default' => 10]);
        $gameSession->addColumn('ev_start', 'integer', ['default' => 15]);
        $gameSession->addColumn('choice_timeout_sec', 'integer', ['default' => 15]);
        $gameSession->addColumn('gps_enabled', 'boolean');
        $gameSession->addColumn('gps_radius_meters', 'integer', ['notnull' => false]);
        $gameSession->addColumn('current_round_index', 'integer', ['default' => 1]);
        $gameSession->addColumn('created_at', 'datetime_immutable');
        $gameSession->addColumn('started_at', 'datetime_immutable', ['notnull' => false]);
        $gameSession->addColumn('finished_at', 'datetime_immutable', ['notnull' => false]);
        $gameSession->addColumn('settings', 'json');
        $gameSession->setPrimaryKey(['id']);
        $gameSession->addUniqueIndex(['game_code'], 'uniq_game_session_code');

        $team = $schema->createTable('team');
        $team->addColumn('id', 'guid');
        $team->addColumn('game_session_id', 'guid');
        $team->addColumn('name', 'string', ['length' => 255]);
        $team->addColumn('members_count', 'integer');
        $team->addColumn('ev_current', 'integer');
        $team->addColumn('status', 'string', ['length' => 16]);
        $team->addColumn('device_id', 'string', ['length' => 255, 'notnull' => false]);
        $team->addColumn('created_at', 'datetime_immutable');
        $team->setPrimaryKey(['id']);
        $team->addIndex(['game_session_id'], 'idx_team_game_session');
        $team->addForeignKeyConstraint('game_session', ['game_session_id'], ['id'], ['onDelete' => 'CASCADE']);

        $round = $schema->createTable('rounds');
        $round->addColumn('id', 'guid');
        $round->addColumn('game_session_id', 'guid');
        $round->addColumn('round_index', 'integer');
        $round->addColumn('title', 'string', ['length' => 255]);
        $round->addColumn('physical_challenge', 'json');
        $round->addColumn('qr_payload_expected', 'string', ['length' => 255]);
        $round->addColumn('time_limit_sec', 'integer');
        $round->addColumn('status', 'string', ['length' => 16]);
        $round->addColumn('start_at', 'datetime_immutable', ['notnull' => false]);
        $round->addColumn('end_at', 'datetime_immutable', ['notnull' => false]);
        $round->setPrimaryKey(['id']);
        $round->addIndex(['game_session_id'], 'idx_round_game_session');
        $round->addUniqueIndex(['game_session_id', 'round_index'], 'uniq_round_game_index');
        $round->addForeignKeyConstraint('game_session', ['game_session_id'], ['id'], ['onDelete' => 'CASCADE']);

        $futureOption = $schema->createTable('future_option');
        $futureOption->addColumn('id', 'guid');
        $futureOption->addColumn('round_id', 'guid');
        $futureOption->addColumn('code', 'string', ['length' => 10]);
        $futureOption->addColumn('title', 'string', ['length' => 255]);
        $futureOption->addColumn('risk_level', 'string', ['length' => 16]);
        $futureOption->addColumn('immediate_effects', 'json');
        $futureOption->addColumn('delayed_effects', 'json');
        $futureOption->addColumn('display_order', 'integer');
        $futureOption->setPrimaryKey(['id']);
        $futureOption->addIndex(['round_id'], 'idx_future_option_round');
        $futureOption->addUniqueIndex(['round_id', 'code'], 'uniq_future_option_code');
        $futureOption->addForeignKeyConstraint('rounds', ['round_id'], ['id'], ['onDelete' => 'CASCADE']);

        $qrScan = $schema->createTable('qr_scan');
        $qrScan->addColumn('id', 'guid');
        $qrScan->addColumn('round_id', 'guid');
        $qrScan->addColumn('team_id', 'guid');
        $qrScan->addColumn('scanned_at', 'datetime_immutable');
        $qrScan->addColumn('client_timestamp', 'datetime_immutable', ['notnull' => false]);
        $qrScan->addColumn('gps_lat', 'float', ['notnull' => false]);
        $qrScan->addColumn('gps_lng', 'float', ['notnull' => false]);
        $qrScan->addColumn('gps_accuracy', 'float', ['notnull' => false]);
        $qrScan->addColumn('scan_status', 'string', ['length' => 20]);
        $qrScan->addColumn('rank', 'integer', ['notnull' => false]);
        $qrScan->addColumn('rejection_reason', 'string', ['length' => 255, 'notnull' => false]);
        $qrScan->setPrimaryKey(['id']);
        $qrScan->addIndex(['round_id'], 'idx_qr_scan_round');
        $qrScan->addIndex(['team_id'], 'idx_qr_scan_team');
        $qrScan->addUniqueIndex(['round_id', 'team_id', 'scan_status'], 'uniq_qr_scan_round_team_status');
        $qrScan->addForeignKeyConstraint('rounds', ['round_id'], ['id'], ['onDelete' => 'CASCADE']);
        $qrScan->addForeignKeyConstraint('team', ['team_id'], ['id'], ['onDelete' => 'CASCADE']);

        $futurePick = $schema->createTable('future_pick');
        $futurePick->addColumn('id', 'guid');
        $futurePick->addColumn('round_id', 'guid');
        $futurePick->addColumn('team_id', 'guid');
        $futurePick->addColumn('future_option_id', 'guid');
        $futurePick->addColumn('picked_at', 'datetime_immutable');
        $futurePick->addColumn('pick_mode', 'string', ['length' => 20]);
        $futurePick->addColumn('locked', 'boolean');
        $futurePick->setPrimaryKey(['id']);
        $futurePick->addIndex(['round_id'], 'idx_future_pick_round');
        $futurePick->addIndex(['team_id'], 'idx_future_pick_team');
        $futurePick->addIndex(['future_option_id'], 'idx_future_pick_option');
        $futurePick->addUniqueIndex(['round_id', 'future_option_id'], 'uniq_future_pick_round_option');
        $futurePick->addUniqueIndex(['round_id', 'team_id'], 'uniq_future_pick_round_team');
        $futurePick->addForeignKeyConstraint('rounds', ['round_id'], ['id'], ['onDelete' => 'CASCADE']);
        $futurePick->addForeignKeyConstraint('team', ['team_id'], ['id'], ['onDelete' => 'CASCADE']);
        $futurePick->addForeignKeyConstraint('future_option', ['future_option_id'], ['id'], ['onDelete' => 'CASCADE']);

        $appliedEffect = $schema->createTable('applied_effect');
        $appliedEffect->addColumn('id', 'guid');
        $appliedEffect->addColumn('game_session_id', 'guid');
        $appliedEffect->addColumn('round_id', 'guid', ['notnull' => false]);
        $appliedEffect->addColumn('team_id', 'guid', ['notnull' => false]);
        $appliedEffect->addColumn('source_type', 'string', ['length' => 20]);
        $appliedEffect->addColumn('effect_type', 'string', ['length' => 32]);
        $appliedEffect->addColumn('payload', 'json');
        $appliedEffect->addColumn('applied_at', 'datetime_immutable');
        $appliedEffect->addColumn('scheduled_round_index', 'integer', ['notnull' => false]);
        $appliedEffect->setPrimaryKey(['id']);
        $appliedEffect->addIndex(['game_session_id'], 'idx_applied_effect_session');
        $appliedEffect->addIndex(['round_id'], 'idx_applied_effect_round');
        $appliedEffect->addIndex(['team_id'], 'idx_applied_effect_team');
        $appliedEffect->addForeignKeyConstraint('game_session', ['game_session_id'], ['id'], ['onDelete' => 'CASCADE']);
        $appliedEffect->addForeignKeyConstraint('rounds', ['round_id'], ['id'], ['onDelete' => 'SET NULL']);
        $appliedEffect->addForeignKeyConstraint('team', ['team_id'], ['id'], ['onDelete' => 'SET NULL']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('applied_effect');
        $schema->dropTable('future_pick');
        $schema->dropTable('qr_scan');
        $schema->dropTable('future_option');
        $schema->dropTable('rounds');
        $schema->dropTable('team');
        $schema->dropTable('game_session');
    }
}
