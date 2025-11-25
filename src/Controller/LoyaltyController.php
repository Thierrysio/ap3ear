<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LoyaltyController extends AbstractController
{
    #[Route('/avantages-fidelite', name: 'app_loyalty_benefits', methods: ['GET'])]
    public function show(): Response
    {
        $products = [
            [
                'name' => 'Dobble',
                'category' => 'Produits Gold',
                'price' => 14.99,
                'description' => 'Jeu convivial idéal pour enrichir votre ludothèque.',
            ],
            [
                'name' => 'Carte cadeau Gold',
                'category' => 'Produits Gold',
                'price' => 24.99,
                'description' => 'Ajoutez un bonus instantané à offrir ou à conserver.',
            ],
            [
                'name' => 'Service premium',
                'category' => 'Produits Gold',
                'price' => 19.99,
                'description' => 'Accès prioritaire et accompagnement personnalisé.',
            ],
            [
                'name' => 'Accès standard',
                'category' => 'Silver',
                'price' => 9.99,
                'description' => 'Vos avantages classiques à portée de main.',
            ],
            [
                'name' => 'Pack découverte',
                'category' => 'Bronze',
                'price' => 4.99,
                'description' => 'Partez à la découverte de nos offres de bienvenue.',
            ],
        ];

        return $this->render('loyalty/benefits.html.twig', [
            'products' => $products,
        ]);
    }
}
