<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Aurelien\Lieu;
use App\Entity\Aurelien\Question;
use App\Entity\Aurelien\Reponse;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AurelienControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testQuestionsEndpointReturnsRandomQuestions(): void
    {
        // Create test data
        $lieu1 = (new Lieu())
            ->setNom('Lieu 1')
            ->setCodeQr('code1')
            ->setImageUrl('https://example.com/image1.jpg');
        $this->em->persist($lieu1);

        $lieu2 = (new Lieu())
            ->setNom('Lieu 2')
            ->setCodeQr('code2')
            ->setImageUrl('https://example.com/image2.jpg');
        $this->em->persist($lieu2);

        // Create 10 questions to test the limit of 5
        for ($i = 1; $i <= 10; $i++) {
            $lieu = $i <= 5 ? $lieu1 : $lieu2;
            
            $question = (new Question())
                ->setLibelle("Question $i")
                ->setLieu($lieu);
            $this->em->persist($question);

            // Add 3 responses for each question
            for ($j = 1; $j <= 3; $j++) {
                $reponse = (new Reponse())
                    ->setLibelle("Réponse $j pour question $i")
                    ->setEstCorrecte($j === 1)
                    ->setQuestion($question);
                $this->em->persist($reponse);
            }
        }

        $this->em->flush();

        // Test the endpoint
        $client = static::createClient();
        $client->request('GET', '/api/mobile/jeu/questions');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);

        // Should return exactly 5 questions
        $this->assertIsArray($data);
        $this->assertCount(5, $data);

        // Check structure of first question
        $firstQuestion = $data[0];
        $this->assertArrayHasKey('id', $firstQuestion);
        $this->assertArrayHasKey('libelle', $firstQuestion);
        $this->assertArrayHasKey('lieu', $firstQuestion);
        $this->assertArrayHasKey('reponses', $firstQuestion);

        // Check lieu structure
        $this->assertArrayHasKey('id', $firstQuestion['lieu']);
        $this->assertArrayHasKey('nom', $firstQuestion['lieu']);

        // Check responses
        $this->assertIsArray($firstQuestion['reponses']);
        $this->assertCount(3, $firstQuestion['reponses']);

        // Check response structure
        $firstResponse = $firstQuestion['reponses'][0];
        $this->assertArrayHasKey('id', $firstResponse);
        $this->assertArrayHasKey('libelle', $firstResponse);
        $this->assertArrayHasKey('est_correcte', $firstResponse);
    }

    public function testQuestionsEndpointWorksWithFewerThanFiveQuestions(): void
    {
        // Create only 2 questions
        $lieu = (new Lieu())
            ->setNom('Lieu Test')
            ->setCodeQr('test_code')
            ->setImageUrl('https://example.com/test.jpg');
        $this->em->persist($lieu);

        for ($i = 1; $i <= 2; $i++) {
            $question = (new Question())
                ->setLibelle("Question $i")
                ->setLieu($lieu);
            $this->em->persist($question);

            $reponse = (new Reponse())
                ->setLibelle("Réponse pour question $i")
                ->setEstCorrecte(true)
                ->setQuestion($question);
            $this->em->persist($reponse);
        }

        $this->em->flush();

        $client = static::createClient();
        $client->request('GET', '/api/mobile/jeu/questions');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        // Should return only 2 questions
        $this->assertIsArray($data);
        $this->assertCount(2, $data);
    }
}
