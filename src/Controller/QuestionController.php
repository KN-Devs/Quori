<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Question;
use App\Form\CommentType;
use App\Form\QuestionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QuestionController extends AbstractController
{
    #[Route('/question/ask', name: 'ask_question')]
    public function ask(Request $request, EntityManagerInterface $em): Response
    {

        $question = new Question();

        $FormQuestion = $this->createForm(QuestionType::class, $question);
        $FormQuestion->handleRequest($request);

        if ($FormQuestion->isSubmitted() && $FormQuestion->isValid()) {
            $question->setNbResponse(0);
            $question->setRating(0);
            $question->setCreatedAt(new \DateTimeImmutable());

            $em->persist($question);
            $em->flush();
            $this->addFlash('sucess', 'Vottre question a bien été ajoutée');
            return $this->redirectToRoute('home');
        }

        return $this->render('question/index.html.twig', ['form' => $FormQuestion->createView()]);
    }

    #[Route('/question/{id}', name: 'show_question')]
    public function show(Request $request, Question $question, EntityManagerInterface $em)
    {      

        $comment = new Comment();
        $commentForm = $this->createForm(CommentType::class, $comment);
        $commentForm->handleRequest($request);
        
        if($commentForm->isSubmitted() && $commentForm->isValid()) {
            $comment->setCreatedAt(new \DateTimeImmutable());
            $comment->setRating(0);
            $comment->setQuestion($question);

            $em->persist($comment);
            $em->flush();

            $this->addFlash('succes', 'Votre repponse a bien était publié');
            return $this->redirect($request->getUri());
        }
        
      return $this->render('question/show.html.twig', ['question' => $question, 'form' => $commentForm->createView()]);
    }
} 

