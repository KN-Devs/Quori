<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserController extends AbstractController
{
    #[Route('/user', name: 'current_user_profile')]
    #[IsGranted("IS_AUTHENTICATED_REMEMBERED")]
    public function currentUserProfile(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em): Response
    {
        /**
         * @var User
         */
        $currentUser = $this->getUser();
        $profileForm = $this->createForm(UserType::class, $currentUser);   
        $profileForm->remove('password');
        $profileForm->add('newPassword', PasswordType::class, [
            'label' => 'Nouveau mot de passe',
            'required' => false,
        ]);
    
        $profileForm->handleRequest($request);
    
        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $newPassword = $currentUser->getNewPassword();
            if ($newPassword) {
                $hashedNewPassword = $passwordHasher->hashPassword($currentUser, $newPassword);
                $currentUser->setPassword($hashedNewPassword);
            }
    
            $em->flush();
            $this->addFlash('success', 'Profil mis à jour');
        }
    
        return $this->render('user/profile.html.twig', [
            'form' => $profileForm->createView(),
        ]);
    }
    
    #[Route('/user/questions', name: 'show_questions')]
    #[IsGranted("IS_AUTHENTICATED_REMEMBERED")]
    public function showQuestions(): Response
    {
        return $this->render('user/show_questions.html.twig');
    }
    
    #[Route('/user/comments', name: 'show_comments')]
    #[IsGranted("IS_AUTHENTICATED_REMEMBERED")]
    public function showComments(): Response
    {
        return $this->render('user/show_comments.html.twig');
    }

    #[Route('/user/{id}', name: 'user_profile')]
    #[IsGranted("IS_AUTHENTICATED_REMEMBERED")]
    public function userProfile(User $user): Response
    {
        $currentUser = $this->getUser();
        if ($currentUser === $user) {
            return $this->redirectToRoute('current_user_profile');
        }
        return $this->render('user/show.html.twig', ['user' => $user]);
    }

}