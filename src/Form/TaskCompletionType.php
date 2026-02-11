<?php

namespace App\Form;

use App\Entity\TaskCompletion;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaskCompletionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('proof', FileType::class, [
            'label' => 'Photo de preuve',
            'mapped' => false,
            'required' => true,

            // 👇 FRONTEND : ne montre QUE les images
            'attr' => [
                'accept' => 'image/*',
            ],

            // 👇 BACKEND : sécurité réelle
            'constraints' => [
                new Assert\NotNull([
                    'message' => 'Veuillez choisir une image',
                ]),
                new Assert\Image([
                    'maxSize' => '5M',
                    'mimeTypesMessage' => 'Seules les images sont autorisées (JPEG, PNG, WEBP)',
                ]),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => TaskCompletion::class,
        ]);
    }
}
