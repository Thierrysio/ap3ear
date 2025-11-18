<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the score column to tequipe.
 */
final class Version20251118151722 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add score column to tequipe';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tequipe ADD _score INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tequipe DROP _score');
    }
}
