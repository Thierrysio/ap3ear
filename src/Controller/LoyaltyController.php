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
            ['name' => 'Dobble',            'category' => 'Produits Gold',   'tier' => 'Gold',   'price' => 12.99],
            ['name' => 'Carte cadeau Gold', 'category' => 'Produits Gold',   'tier' => 'Gold',   'price' => 14.99],
            ['name' => 'Service premium',   'category' => 'Produits Gold',   'tier' => 'Gold',   'price' => 19.99],
            ['name' => 'Accès standard',    'category' => 'Produits Silver', 'tier' => 'Silver', 'price' => 9.99],
            ['name' => 'Pack découverte',   'category' => 'Produits Bronze', 'tier' => 'Bronze', 'price' => 4.99],
        ];

        return $this->render('loyalty/benefits.html.twig', [
            'products' => $products,
        ]);
    }
}
