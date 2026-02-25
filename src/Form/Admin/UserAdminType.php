<?php

namespace App\Form\Admin;

use App\Entity\Family;
use App\Entity\User;
use App\Enum\FamilyRole;
use App\Enum\SystemRole;
use App\Enum\UserStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = (bool) ($options['is_edit'] ?? false);

        $builder
            ->add('email', TextType::class, [
                'required' => false,
                'constraints' => [
                    new NotBlank(message: 'Email is required.'),
                    new EmailConstraint(message: 'Please provide a valid email address.'),
                ],
                'help' => 'Use a valid email format, e.g. name@example.com.',
            ])
            ->add('username', TextType::class, [
                'required' => false,
                'constraints' => [
                    new NotBlank(message: 'Username is required.'),
                    new Length(min: 4, max: 40),
                ],
                'help' => '4 to 40 characters. Letters, numbers, and underscores recommended.',
            ])
            ->add('firstName', TextType::class, [
                'required' => false,
                'constraints' => [
                    new NotBlank(message: 'First name is required.'),
                    new Length(max: 255),
                ],
                'help' => 'Legal or preferred first name.',
            ])
            ->add('lastName', TextType::class, [
                'required' => false,
                'constraints' => [
                    new NotBlank(message: 'Last name is required.'),
                    new Length(max: 255),
                ],
                'help' => 'Legal or preferred last name.',
            ])
            ->add('systemRole', ChoiceType::class, [
                'required' => false,
                'choices' => $this->enumChoices(SystemRole::cases()),
                'choice_label' => fn (SystemRole $role) => ucfirst(strtolower($role->name)),
                'choice_value' => static fn (?SystemRole $role) => $role?->value,
                'constraints' => [new NotBlank(message: 'System role is required.')],
                'help' => 'Controls access level across the app.',
            ])
            ->add('status', ChoiceType::class, [
                'required' => false,
                'choices' => $this->enumChoices(UserStatus::cases()),
                'choice_label' => fn (UserStatus $status) => ucfirst(strtolower($status->name)),
                'choice_value' => static fn (?UserStatus $status) => $status?->value,
                'constraints' => [new NotBlank(message: 'Status is required.')],
                'help' => 'Suspended users cannot sign in.',
            ])
            ->add('familyRole', ChoiceType::class, [
                'required' => false,
                'choices' => $this->enumChoices(FamilyRole::cases()),
                'choice_label' => fn (FamilyRole $role) => ucfirst(strtolower($role->name)),
                'choice_value' => static fn (?FamilyRole $role) => $role?->value,
                'constraints' => [new NotBlank(message: 'Family role is required.')],
                'help' => 'Defines role within the household.',
            ])
            ->add('birthDate', DateType::class, [
                'required' => false,
                'widget' => 'single_text',
                'html5' => false,
                'constraints' => [new NotBlank(message: 'Birth date is required.')],
                'help' => 'Format: YYYY-MM-DD.',
            ])
            ->add('family', EntityType::class, [
                'required' => false,
                'class' => Family::class,
                'choice_label' => fn (Family $family) => sprintf('%s (#%d)', $family->getName(), $family->getId()),
                'placeholder' => 'No family',
            ])
            ->add('locale', TextType::class, [
                'required' => false,
                'constraints' => [
                    new NotBlank(message: 'Locale is required.'),
                    new Length(max: 10),
                ],
                'help' => 'Language code like en or fr.',
            ])
            ->add('timeZone', TextType::class, [
                'required' => false,
                'constraints' => [new Length(max: 64)],
                'help' => 'Timezone like UTC or America/New_York.',
            ]);

        if (!$isEdit) {
            $builder->add('password', PasswordType::class, [
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new NotBlank(message: 'Password is required.'),
                    new Length(min: 8),
                ],
                'help' => 'At least 8 characters.',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'csrf_protection' => true,
            'is_edit' => false,
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
