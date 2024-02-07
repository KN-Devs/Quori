<?php

namespace App\Controller;

use App\Form\QuestionType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QuestionController extends AbstractController
{
    #[Route('/question/ask', name: 'ask_question')]
    public function index(Request $request): Response
    {
        $FormQuestion = $this->createForm(QuestionType::class);
        $FormQuestion->handleRequest($request);

        if ($FormQuestion->isSubmitted() && $FormQuestion->isValid()) {
            dump($FormQuestion->getData());
        }

            return $this->render('question/index.html.twig', ['form' => $FormQuestion->createView()]);
    }
}
