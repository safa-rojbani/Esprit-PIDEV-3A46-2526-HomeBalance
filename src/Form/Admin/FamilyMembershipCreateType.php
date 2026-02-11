<?php

namespace App\Form\Admin;

use App\DTO\FamilyMembershipInput;
use App\Entity\Family;
use App\Entity\User;
use App\Enum\FamilyRole;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class FamilyMembershipCreateType extends AbstractType
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
            ->add('user', EntityType::class, [
                'required' => false,
                'class' => User::class,
                'choice_label' => fn (User $user) => $user->getUserIdentifier(),
                'constraints' => [new NotBlank(message: 'User is required.')],
            ])
            ->add('role', ChoiceType::class, [
                'required' => false,
                'choices' => $this->enumChoices(FamilyRole::cases()),
                'choice_label' => fn (FamilyRole $role) => ucfirst(strtolower($role->name)),
                'choice_value' => static fn (?FamilyRole $role) => $role?->value,
                'constraints' => [new NotBlank(message: 'Role is required.')],
            ])
            ->add('joinedAt', DateTimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'html5' => false,
                'input' => 'datetime_immutable',
            ])
            ->add('leftAt', DateTimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'html5' => false,
                'input' => 'datetime_immutable',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FamilyMembershipInput::class,
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
