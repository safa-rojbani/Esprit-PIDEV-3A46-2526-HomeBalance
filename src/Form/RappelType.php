<?php

namespace App\Form;

use App\Entity\Rappel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RappelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('offsetMinutes', IntegerType::class, [
                'constraints' => [new Assert\NotBlank(), new Assert\Positive()],
                'label' => 'Minutes before event',
            ])
            ->add('canal', ChoiceType::class, [
                'choices' => [
                    'Popup' => 'popup',
                    'Email' => 'email',
                    'SMS' => 'sms',
                ],
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('actif', CheckboxType::class, [
                'required' => false,
                'label' => 'Active',
            ])
            ->add('estLu', CheckboxType::class, [
                'required' => false,
                'label' => 'Read',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Rappel::class,
        ]);
    }
}
