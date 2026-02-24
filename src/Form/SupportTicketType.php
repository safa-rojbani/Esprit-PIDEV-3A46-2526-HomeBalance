<?php

namespace App\Form;

use App\Entity\SupportTicket;
use App\Enum\PrioritySupportTicket;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class SupportTicketType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('subject', TextType::class, [
                'label' => 'Subject',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Brief description of your issue',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Please enter a subject.'),
                    new Assert\Length(
                        min: 5,
                        max: 255,
                        minMessage: 'Subject must be at least {{ limit }} characters.',
                        maxMessage: 'Subject cannot exceed {{ limit }} characters.',
                    ),
                ],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 6,
                    'placeholder' => 'Describe your issue in detail...',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Please enter a message.'),
                    new Assert\Length(
                        min: 10,
                        minMessage: 'Message must be at least {{ limit }} characters.',
                    ),
                ],
            ])
            ->add('priority', EnumType::class, [
                'class' => PrioritySupportTicket::class,
                'label' => 'Priority',
                'attr' => ['class' => 'form-select'],
                'choice_label' => fn (PrioritySupportTicket $p) => ucfirst($p->value),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SupportTicket::class,
        ]);
    }
}
