<?php

namespace App\Form\ModuleCharge;

use App\Entity\HistoriqueAchat;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HistoriqueAchatConfirmType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('montantAchete', MoneyType::class, [
                'label' => 'Montant',
                'currency' => 'TND',
                'required' => true,
            ])
            ->add('quantiteAchete', IntegerType::class, [
                'label' => 'Quantité',
                'required' => false,
                'empty_data' => '1',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => HistoriqueAchat::class,
        ]);
    }
}
