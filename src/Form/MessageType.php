<?php

namespace App\Form;

use App\Entity\Message;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class MessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'label'    => false,
                'required' => false,
                'attr'     => [
                    'placeholder' => 'Type your message...',
                    'class'       => 'form-control message-input',
                    'rows'        => 1,
                ],
            ])
            // Unmapped hidden field — controller reads it to set parentMessage relation
            ->add('parentMessageId', HiddenType::class, [
                'label'    => false,
                'required' => false,
                'mapped'   => false,
                'attr'     => ['data-messaging-target' => 'parentMessageId'],
            ])
            ->add('attachment', \Symfony\Component\Form\Extension\Core\Type\FileType::class, [
            'label' => false,
            'required' => false,
            'mapped' => false,
            'attr' => [
                'class' => 'd-none',
                'accept' => 'image/*',
            ],
            'constraints' => [
                new Assert\File([
                    'maxSize' => '5M',
                    'mimeTypes' => [
                        'image/jpeg',
                        'image/png',
                        'image/gif',
                        'image/webp',
                    ],
                    'mimeTypesMessage' => 'Please upload a valid image (JPEG, PNG, GIF, WEBP)',
                ])
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Message::class ,
        ]);
    }
}
