<?php

namespace App\Form\ModuleDocuments\FrontOffice;

use App\Entity\DefaultGallery;
use App\Entity\Gallery;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GalleryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('defaultGallery', EntityType::class, [
                'class' => DefaultGallery::class,
                'choice_label' => 'name',
                'placeholder' => '— Choose Default Gallery —',
                'required' => false,
                // ✅ We attach data to each option for JS auto-fill
                'choice_attr' => function (?DefaultGallery $dg) {
                    if (!$dg) return [];
                    return [
                        'data-name' => $dg->getName(),
                        'data-description' => $dg->getDescription() ?? '',
                    ];
                },
            ])
            ->add('name')
            ->add('description')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Gallery::class,
            'attr' => [
                'novalidate' => 'novalidate',
            ],
        ]);
    }
}
