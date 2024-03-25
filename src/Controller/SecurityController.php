<?php

namespace App\Controller;

use App\Entity\ResetPassword;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\ResetPasswordRepository;
use App\Repository\UserRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class SecurityController extends AbstractController
{
    // Déclaration du constructeur avec l'injection de dépendance pour $formLoginAuthenticator
    function __construct(private $formLoginAuthenticator)
    {

    }

    // Route pour l'inscription utilisateur
    #[Route('/signup', name: 'signup')]
    public function signup(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em, UserAuthenticatorInterface $userAuthenticator, MailerInterface $mailer): Response
    {
        // Création d'une nouvelle instance de l'entité User
        $user = new User();
        // Création du formulaire d'inscription en utilisant le UserType
        $signupForm = $this->createForm(UserType::class, $user);
        // Traitement de la requête HTTP
        $signupForm->handleRequest($request);

        // Vérification si le formulaire est soumis et valide
        if ($signupForm->isSubmitted() && $signupForm->isValid()) {
            // Hashage du mot de passe de l'utilisateur
            $hashedPassword = $passwordHasher->hashPassword($user, $user->getPassword());
            $user->setPassword($hashedPassword);

            $picture = $signupForm->get('pictureFile')->getData();
            if($picture){
                $folder = $this->getParameter('profile.folder');
                $ext = $picture->guessExtension() ?? 'bin';
                $fileName =bin2hex(random_bytes(10)).'.'.$ext;
                $picture->move($folder, $fileName);
                $user->setImage($this->getParameter('profile.folder.public_path'). "/" .$fileName);;
            }else{
                $user->setImage("/images/default_profile.png");
            }
            // Persistance de l'utilisateur en base de données
            $em->persist($user);
            $em->flush();

            // Envoi d'un e-mail de bienvenue
            $email = new TemplatedEmail();
            $email->to($user->getEmail())
                ->subject('Bienvenue sur Quori')
                ->htmlTemplate('@email_templates/welcome.html.twig')
                ->context([
                    'fullname' => $user->getFullName()
                ]);
            $mailer->send($email);

            // Message flash pour notifier l'utilisateur que son compte a été créé
            $this->addFlash('success', 'Votre compte a bien été créé');

            // Authentification de l'utilisateur après l'inscription
            return $userAuthenticator->authenticateUser($user, $this->formLoginAuthenticator, $request);
        }

        // Affichage du formulaire d'inscription
        return $this->render('security/signup.html.twig', ['form' => $signupForm->createView()]);
    }

    // Route pour la connexion utilisateur
    #[Route('/signin', name: 'signin')]
    public function signin(AuthenticationUtils $authenticationUtils): Response
    {
        
        // Vérification si l'utilisateur est déjà connecté, redirection vers la page d'accueil
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        // Récupération de l'éventuelle erreur d'authentification
        $error = $authenticationUtils->getLastAuthenticationError();
        $username = $authenticationUtils->getLastUsername();

        // Affichage du formulaire de connexion
        return $this->render('security/signin.html.twig', [
            'error' => $error,
            'username' => $username
        ]);
    }

    // Route pour la déconnexion utilisateur
    #[Route('/logout', name: 'logout')]
    public function logout() {}

    // Route pour la demande de réinitialisation de mot de passe
    #[Route('/reset-password-request', name: 'reset-password-request')]
    public function resetPasswordRequest(Request $request, UserRepository $userRepository, EntityManagerInterface $em, ResetPasswordRepository $resetPasswordRepository, MailerInterface $mailer, RateLimiterFactory $passwordRecoveryLimiter) {

        $limiter = $passwordRecoveryLimiter->create($request->getClientIp());



        // Création du formulaire de demande de réinitialisation de mot de passe
        $emailForm = $this->createFormBuilder()
            ->add('email', EmailType::class)
            ->getForm();

        // Traitement de la requête HTTP
        $emailForm->handleRequest($request);

        // Vérification si le formulaire est soumis et valide
        if ($emailForm->isSubmitted() && $emailForm->isValid()) {

            if (!$limiter->consume(1)->isAccepted()) {
                $this->addFlash('error', 'Vous avez atteint la limite de demandes de réinitialisation, veuillez réessayer dans une heure.');
                return $this->redirectToRoute('signin');
            }

            // Récupération de l'e-mail fourni dans le formulaire
            $email = $emailForm->get('email')->getData();
            // Recherche de l'utilisateur associé à l'e-mail
            $user = $userRepository->findOneBy(['email' => $email]);

            // Vérification si l'utilisateur existe
            if($user){

                // Suppression d'une éventuelle demande de réinitialisation précédente pour cet utilisateur
                $oldResetPassword = $resetPasswordRepository->findOneBy(['user' => $user]);
                if($oldResetPassword){
                    $em->remove($oldResetPassword);
                    $em->flush();
                }

                // Génération d'un jeton de réinitialisation de mot de passe
                $token = substr(str_replace(['/','+','-',], '', base64_encode(random_bytes(40))), 0, 20);

                // Création d'une nouvelle instance de ResetPassword
                $resetPassword = new ResetPassword();
                $resetPassword->setUser($user)
                    ->setToken(sha1($token))
                    ->setExpiredAt(new DateTimeImmutable('+2 hours'));  
                
                // Persistance de la demande de réinitialisation en base de données
                $em->persist($resetPassword);
                $em->flush();

                // Envoi d'un e-mail avec le lien de réinitialisation
                $resetemail = new TemplatedEmail();
                $resetemail->to($user->getEmail())
                    ->subject('Réinitialisation de votre mot de passe')
                    ->htmlTemplate('@email_templates/reset-password-request.html.twig')
                    ->context([
                        'fullname' => $user->getFullName(),
                        'token' => $token
                    ]);
                
                $mailer->send($resetemail);

                // Message flash pour notifier l'utilisateur de l'envoi de l'e-mail
                $this->addFlash('success', 'Un email vous a été envoyé pour réinitialiser votre mot de passe');
                // Redirection vers la page de connexion
                return $this->redirectToRoute('signin');

            }else{
                // Message flash si aucun utilisateur n'est associé à cet e-mail
                $this->addFlash('error', 'Aucun compte n\'est associé à cette adresse email');
            }
        }

        // Affichage du formulaire de demande de réinitialisation de mot de passe
        return $this->render('security/reset-password-request.html.twig', ['form' => $emailForm->createView ()]);
    }

    // Route pour la réinitialisation de mot de passe via un lien
    #[Route('/reset-password/{token}', name: 'reset-password')]
    public function resetPassword(string $token, ResetPasswordRepository $resetPasswordRepository, Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em, RateLimiterFactory $passwordRecoveryLimiter) {

        $limiter = $passwordRecoveryLimiter->create($request->getClientIp());


        if (!$limiter->consume(1)->isAccepted()) {
            $this->addFlash('error', 'Vous avez atteint la limite de demandes de réinitialisation, veuillez réessayer dans une heure.');
            return $this->redirectToRoute('signin');
        }
        

        // Recherche de la demande de réinitialisation associée au jeton fourni
        $resetPassword = $resetPasswordRepository->findOneBy(['token' => sha1($token)]);

        // Vérification si la demande de réinitialisation existe et si elle est encore valide
        if(!$resetPassword || $resetPassword->getExpiredAt() < new DateTime('now')){
            // Si la demande n'existe pas ou a expiré, affichage d'un message d'erreur
            // et redirection vers la page de demande de réinitialisation de mot de passe
            if($resetPassword) {
                $em->remove($resetPassword);
                $em->flush();
            }

            $this->addFlash('error', 'Ce lien de réinitialisation de mot de passe est invalide ou a expiré');
            return $this->redirectToRoute('reset-password-request');
        }

        // Création du formulaire de réinitialisation de mot de passe
        $resetPasswordForm = $this->createFormBuilder()
            ->add('password', PasswordType::class,[
                'label' => 'Nouveau mot de passe',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer un mot de passe' 
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Votre mot de passe doit contenir au moins 6 caractères'
                    ])
                ]
            ])
            ->getForm();
        // Traitement de la requête HTTP
        $resetPasswordForm->handleRequest($request);

        // Vérification si le formulaire est soumis et valide
        if($resetPasswordForm->isSubmitted() && $resetPasswordForm->isValid()){
            // Récupération de l'utilisateur associé à la demande de réinitialisation
            $user = $resetPassword->getUser();

            // Récupération du nouveau mot de passe depuis le formulaire
            $newPassword = $resetPasswordForm->get('password')->getData();
            // Hashage du nouveau mot de passe
            $hashedNewPassword = $passwordHasher->hashPassword($user, $newPassword);
            // Mise à jour du mot de passe de l'utilisateur en base de données
            $user->setPassword($hashedNewPassword);
            // Suppression de la demande de réinitialisation après utilisation
            $em->remove($resetPassword);
            $em->flush();

            // Message flash pour notifier l'utilisateur que son mot de passe a été réinitialisé
            $this->addFlash('success', 'Votre mot de passe a bien été réinitialisé');
            // Redirection vers la page de connexion
            return $this->redirectToRoute('signin');
        }

        // Affichage du formulaire de réinitialisation de mot de passe
        return $this->render('security/reset-password-form.html.twig', ['form' => $resetPasswordForm->createView()]);
    }
}

