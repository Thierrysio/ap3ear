<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250504120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pivot table to link competitions and teams.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tcompetition_tequipe (tcompetition_id INT NOT NULL, tequipe_id INT NOT NULL, INDEX IDX_TCOMP_TEAM_COMP (tcompetition_id), INDEX IDX_TCOMP_TEAM_TEAM (tequipe_id), PRIMARY KEY(tcompetition_id, tequipe_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE tcompetition_tequipe ADD CONSTRAINT FK_TCOMP_TEAM_COMP FOREIGN KEY (tcompetition_id) REFERENCES tcompetition (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tcompetition_tequipe ADD CONSTRAINT FK_TCOMP_TEAM_TEAM FOREIGN KEY (tequipe_id) REFERENCES tequipe (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tcompetition_tequipe');
    }
}
