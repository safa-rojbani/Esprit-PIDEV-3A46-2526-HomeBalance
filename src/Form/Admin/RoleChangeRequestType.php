<?php

namespace App\Form\Admin;

use App\Entity\RoleChangeRequest;
use App\Enum\SystemRole;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RoleChangeRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('requestedRole', ChoiceType::class, [
            'choices' => $this->enumChoices(SystemRole::cases()),
            'choice_label' => static fn (SystemRole $role): string => ucfirst(strtolower($role->name)),
            'choice_value' => static fn (?SystemRole $role): ?string => $role?->value,
            'label' => 'Requested role',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RoleChangeRequest::class,
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
