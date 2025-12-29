<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tepreuve_id and tequipe_id bindings to game sessions and teams';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('game_session');
        if (!$table->hasColumn('tepreuve_id')) {
            $table->addColumn('tepreuve_id', 'integer', ['notnull' => false]);
        }

        $teamTable = $schema->getTable('team');
        if (!$teamTable->hasColumn('tequipe_id')) {
            $teamTable->addColumn('tequipe_id', 'integer', ['notnull' => false]);
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('game_session');
        if ($table->hasColumn('tepreuve_id')) {
            $table->dropColumn('tepreuve_id');
        }

        $teamTable = $schema->getTable('team');
        if ($teamTable->hasColumn('tequipe_id')) {
            $teamTable->dropColumn('tequipe_id');
        }
    }
}
