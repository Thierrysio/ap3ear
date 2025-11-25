<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NohadApiController extends AbstractController
{
    #[Route('/nohad/api', name: 'app_nohad_api')]
    public function index(): Response
    {
        return $this->render('nohad_api/index.html.twig', [
            'controller_name' => 'NohadApiController',
        ]);
    }
}
