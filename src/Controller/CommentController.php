<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Vote;
use App\Repository\VoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CommentController extends AbstractController
{
    #[Route('/comment/rating/{id}/{score}', name: 'comment_rating')]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function rateComment(Request $request,Comment $comment,int $score, EntityManagerInterface $em, VoteRepository $voteRepository)
    {
        $currentUser = $this->getUser();

        if($currentUser !== $comment->getAuthor()) {

            

            $vote = $voteRepository->findOneBy([
                'author' => $currentUser,
                'comment' => $comment,
            ]);

            if($vote) {
                if(($vote->getIsLiked() && $score > 0) || (!$vote->getIsLiked() && $score < 0)) {
                    $em->remove($vote);
                    $comment->setRating($comment->getRating() + ( $score > 0 ? -1 : 1 ));
                } else {
                    $vote->setIsLiked(!$vote->getIsLiked());
                    $comment->setRating($comment->getRating() + ( $score > 0 ? 2 : -2 ));

                } 
            } else {
                $newVote = new Vote();
                $newVote->setAuthor($currentUser)
                        ->setComment($comment)
                        ->setIsLiked($score > 0 ? true : false);
                $em->persist($newVote);
                $comment->setRating($comment->getRating() + $score);
            
            }   

            $em->flush();
            
        }

        $referer = $request->server->get('HTTP_REFERER');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('home');
    }
}
