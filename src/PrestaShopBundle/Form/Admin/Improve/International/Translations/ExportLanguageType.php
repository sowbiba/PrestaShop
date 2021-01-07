<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace PrestaShopBundle\Form\Admin\Improve\International\Translations;

use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class ExportLanguageType is responsible for building export language form
 * in 'Improve > International > Translations' page.
 */
class ExportLanguageType extends TranslatorAwareType
{
    /**
     * @var array
     */
    private $themeChoices;
    /**
     * @var array
     */
    private $moduleChoices;
    /**
     * @var array
     */
    private $translationExportTypeChoices;

    /**
     * @param TranslatorInterface $translator
     * @param array $locales
     * @param array $translationExportTypeChoices
     * @param array $themeChoices
     * @param array $moduleChoices
     */
    public function __construct(
        TranslatorInterface $translator,
        array $locales,
        array $translationExportTypeChoices,
        array $themeChoices,
        array $moduleChoices
    ) {
        parent::__construct($translator, $locales);
        $this->translationExportTypeChoices = $translationExportTypeChoices;
        $this->themeChoices = $themeChoices;
        $this->moduleChoices = $moduleChoices;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('iso_code', ChoiceType::class, [
                'choices' => $this->getLocaleChoices(),
                'choice_translation_domain' => false,
            ])
            ->add('translation_export_type', ChoiceType::class, [
                'choices' => $this->translationExportTypeChoices,
                'choice_translation_domain' => false,
            ])
            ->add('theme_name', ChoiceType::class, [
                'choices' => $this->themeChoices,
                'choice_translation_domain' => false,
            ])
            ->add('module_name', ChoiceType::class, [
                'placeholder' => '---',
                'choices' => $this->moduleChoices,
                'choice_translation_domain' => false,
            ]);
    }
}
