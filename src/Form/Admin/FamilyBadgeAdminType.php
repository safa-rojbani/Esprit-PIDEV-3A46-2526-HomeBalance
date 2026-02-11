<?php

namespace App\Form\Admin;

use App\Entity\Badge;
use App\Entity\Family;
use App\Entity\FamilyBadge;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class FamilyBadgeAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('family', EntityType::class, [
                'required' => false,
                'class' => Family::class,
                'choice_label' => fn (Family $family) => sprintf('%s (#%d)', $family->getName(), $family->getId()),
                'constraints' => [new NotBlank(message: 'Family is required.')],
            ])
            ->add('badge', EntityType::class, [
                'required' => false,
                'class' => Badge::class,
                'choice_label' => fn (Badge $badge) => sprintf('%s (%s)', $badge->getName(), $badge->getCode()),
                'constraints' => [new NotBlank(message: 'Badge is required.')],
            ])
            ->add('awardedAt', DateTimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'html5' => false,
                'input' => 'datetime_immutable',
                'constraints' => [new NotBlank(message: 'Awarded at is required.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FamilyBadge::class,
            'csrf_protection' => true,
        ]);
    }
}
