<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251201000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create NohadQuestion and NohadReponse tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE nohad_question (id INT AUTO_INCREMENT NOT NULL, enonce VARCHAR(255) NOT NULL, duree_secondes INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE nohad_reponse (id INT AUTO_INCREMENT NOT NULL, question_id INT NOT NULL, identifiant VARCHAR(255) NOT NULL, texte VARCHAR(255) NOT NULL, INDEX IDX_97EE18F31E27F6BF (question_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE nohad_reponse ADD CONSTRAINT FK_97EE18F31E27F6BF FOREIGN KEY (question_id) REFERENCES nohad_question (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE nohad_reponse DROP FOREIGN KEY FK_97EE18F31E27F6BF');
        $this->addSql('DROP TABLE nohad_reponse');
        $this->addSql('DROP TABLE nohad_question');
    }
}
