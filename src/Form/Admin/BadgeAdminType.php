<?php

namespace App\Form\Admin;

use App\Entity\Badge;
use App\Enum\BadgeScope;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class BadgeAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'required' => false,
                'constraints' => [
                    new NotBlank(message: 'Badge name is required.'),
                    new Length(max: 255),
                ],
            ])
            ->add('code', TextType::class, [
                'required' => false,
                'constraints' => [
                    new NotBlank(message: 'Badge code is required.'),
                    new Length(max: 64),
                ],
            ])
            ->add('scope', ChoiceType::class, [
                'required' => false,
                'choices' => $this->enumChoices(BadgeScope::cases()),
                'choice_label' => fn (BadgeScope $scope) => ucfirst(strtolower($scope->name)),
                'choice_value' => static fn (?BadgeScope $scope) => $scope?->value,
            ])
            ->add('description', TextType::class, [
                'required' => false,
                'constraints' => [new Length(max: 255)],
            ])
            ->add('icon', TextType::class, [
                'required' => false,
                'constraints' => [new Length(max: 255)],
            ])
            ->add('requiredPoints', TextType::class, [
                'required' => false,
                'constraints' => [
                    new NotBlank(message: 'Required points are needed.'),
                    new Regex(pattern: '/^\d+$/', message: 'Required points must be a number.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Badge::class,
            'csrf_protection' => true,
        ]);
    }

    /**
     * @param array<int, \UnitEnum> $cases
     * @return array<string, \UnitEnum>
     */
    private function enumChoices(array $cases): array
    {
        $choices = [];
        foreach ($cases as $case) {
            $choices[$case->value] = $case;
        }

        return $choices;
    }
}
