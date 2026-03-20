<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des tables du jeu de piste (lieu, question, réponse).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE aurelien_lieu (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, image_url VARCHAR(255) NOT NULL, code_qr VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE aurelien_question (id INT AUTO_INCREMENT NOT NULL, lieu_id INT NOT NULL, libelle VARCHAR(255) NOT NULL, INDEX IDX_5C68E9436AB213CC (lieu_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE aurelien_reponse (id INT AUTO_INCREMENT NOT NULL, question_id INT NOT NULL, libelle VARCHAR(255) NOT NULL, est_correcte TINYINT(1) NOT NULL, INDEX IDX_524F3DF21E27F6BF (question_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE aurelien_question ADD CONSTRAINT FK_5C68E9436AB213CC FOREIGN KEY (lieu_id) REFERENCES aurelien_lieu (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE aurelien_reponse ADD CONSTRAINT FK_524F3DF21E27F6BF FOREIGN KEY (question_id) REFERENCES aurelien_question (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE aurelien_reponse DROP FOREIGN KEY FK_524F3DF21E27F6BF');
        $this->addSql('ALTER TABLE aurelien_question DROP FOREIGN KEY FK_5C68E9436AB213CC');
        $this->addSql('DROP TABLE aurelien_reponse');
        $this->addSql('DROP TABLE aurelien_question');
        $this->addSql('DROP TABLE aurelien_lieu');
    }
}
