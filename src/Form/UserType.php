<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname', TextType::class, [
                'label' => 'Prénom *', 
                'required' => false
            ])
            ->add('lastname', TextType::class, [
                'label' => 'Nom *', 
                'required' => false
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email *',
                'required' => false
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Mot de passe *',
                'required' => false
            ])
            ->add('pictureFile', FileType::class, [
                'label' => 'Photo de profil', 
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Image([
                        'maxSize' => '1M',
                        'maxSizeMessage' => 'La taille de votre image est de {{ size }}, l\'image ne doit pas dépasser {{ limit }} {{ suffix }}',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif'
                        ],
                        'mimeTypesMessage' => 'Le fichier doit être une image au format jpeg, png ou gif'
                    ])
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
