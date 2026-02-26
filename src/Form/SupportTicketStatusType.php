<?php

namespace App\Form;

use App\Enum\StatusSupportTicket;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SupportTicketStatusType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', EnumType::class , [
            'class' => StatusSupportTicket::class ,
            'label' => 'Status',
            'attr' => ['class' => 'form-select'],
            'choice_label' => fn(StatusSupportTicket $s) => match ($s) {
            StatusSupportTicket::OPEN => 'Open',
            StatusSupportTicket::IN_PROGRESS => 'In Progress',
            StatusSupportTicket::CLOSED => 'Closed',
        },
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
