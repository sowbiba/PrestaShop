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

declare(strict_types=1);

namespace Tests\Integration\PrestaShopBundle\Translation\Provider;

use PrestaShopBundle\Translation\Loader\DatabaseTranslationReader;
use PrestaShopBundle\Translation\Provider\BackProvider;
use PrestaShopBundle\Translation\Provider\Factory\ProviderFactory;
use PrestaShopBundle\Translation\Provider\FrontProvider;
use PrestaShopBundle\Translation\Provider\ModulesProvider;
use PrestaShopBundle\Translation\Provider\TranslationsCatalogueProvider;
use PrestaShopBundle\Translation\Provider\Type\BackType;
use PrestaShopBundle\Translation\Provider\Type\FrontType;
use PrestaShopBundle\Translation\Provider\Type\ModulesType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TranslationsCatalogueProviderTest extends KernelTestCase
{
    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface|null
     */
    private $container;

    /**
     * @var DatabaseTranslationReader|null
     */
    private $databaseReader;

    protected function setUp()
    {
        self::bootKernel();
        $this->container = self::$kernel->getContainer();
        $databaseContent = [
            [
                'lang' => 'fr-FR',
                'key' => 'Invalid name',
                'translation' => 'Traduction customisée',
                'domain' => 'ShopFormsErrors',
                'theme' => null,
            ],
            [
                'lang' => 'fr-FR',
                'key' => 'Some made up text',
                'translation' => 'Un texte inventé',
                'domain' => 'ShopActions',
                'theme' => 'classic',
            ],
        ];
        $this->databaseReader = new MockDatabaseTranslationReader([]);

        $langId = \Language::getIdByIso('fr', true);
        if (!$langId) {
            $lang = new \Language();
            $lang->locale = 'fr-FR';
            $lang->iso_code = 'fr';
            $lang->name = 'Français';
            $lang->add();
        }
    }

    /**
     * The provider should retrieve all translations from files that
     * look like `AdminSomething` or `ModulesSomethingAdmin`
     */
    public function testGetCatalogueForBackoffice()
    {
        $providerFactory = $this->createMock(ProviderFactory::class);

        $providerFactory
            ->expects($this->any())
            ->method('build')
            ->willReturn(
                new BackProvider(
                    $this->databaseReader,
                    $this->getDefaultTranslationsDirectory()
                )
            );

        $provider = new TranslationsCatalogueProvider($providerFactory);

        $messages = $provider->getCatalogue(new BackType(), 'fr-FR');
        $this->assertIsArray($messages);

        // Check integrity of translations
        $this->assertArrayHasKey('AdminActions', $messages);

        $this->assertCount(92, $messages['AdminActions']);
        $this->assertArrayHasKey('__metadata', $messages['AdminActions']);
        $this->assertArrayHasKey('missing_translations', $messages['AdminActions']['__metadata']);
        $this->assertSame(91, $messages['AdminActions']['__metadata']['count']);
        $this->assertArrayHasKey('Download file', $messages['AdminActions']);
        $this->assertSame([
            'default' => 'Download file',
            'xliff' => 'Télécharger le fichier',
            'database' => null,
        ], $messages['AdminActions']['Download file']);
    }

    /**
     * The provider should retrieve all translations from files that
     * look like `AdminSomething` or `ModulesSomethingAdmin`
     */
    public function testGetCatalogueForFrontoffice()
    {
        $providerFactory = $this->createMock(ProviderFactory::class);

        $providerFactory
            ->expects($this->any())
            ->method('build')
            ->willReturn(
                new FrontProvider(
                    $this->databaseReader,
                    $this->getDefaultTranslationsDirectory()
                )
            );

        $provider = new TranslationsCatalogueProvider($providerFactory);

        $messages = $provider->getCatalogue(new FrontType(), 'fr-FR');
        $this->assertIsArray($messages);

        // Check integrity of translations
        $this->assertArrayHasKey('ShopNotificationsWarning', $messages);

        $this->assertCount(9, $messages['ShopNotificationsWarning']);
        $this->assertArrayHasKey('You do not have any vouchers.', $messages['ShopNotificationsWarning']);
        $this->assertSame(
            [
                'default' => 'You do not have any vouchers.',
                'xliff' => 'Vous ne possédez pas de bon de réduction.',
                'database' => null,
            ],
            $messages['ShopNotificationsWarning']['You do not have any vouchers.']
        );
        $this->assertArrayHasKey('__metadata', $messages['ShopNotificationsWarning']);
        $this->assertArrayHasKey('missing_translations', $messages['ShopNotificationsWarning']['__metadata']);
        $this->assertSame(8, $messages['ShopNotificationsWarning']['__metadata']['count']);
    }

    /**
     * The provider should retrieve all translations from files that
     * look like `AdminSomething` or `ModulesSomethingAdmin`
     */
    public function testGetCatalogueForModules()
    {
        $providerFactory = $this->createMock(ProviderFactory::class);

        $providerFactory
            ->expects($this->any())
            ->method('build')
            ->willReturn(
                new ModulesProvider(
                    $this->databaseReader,
                    $this->getBuiltInModuleDirectory(),
                    $this->getDefaultTranslationsDirectory(),
                    $this->container->get('prestashop.translation.loader.legacy_file'),
                    $this->container->get('prestashop.translation.extractor.legacy_module'),
                    'checkpayment'
                )
            );

        $provider = new TranslationsCatalogueProvider($providerFactory);

        $messages = $provider->getCatalogue(new ModulesType('checkpayment'), 'fr-FR');
        $this->assertIsArray($messages);

        // check only the specific module translations have been loaded
        $domains = array_keys($messages);
        // for some reason domains may not be in the same order as the test
        sort($domains);
        $this->assertSame(['ModulesCheckpaymentAdmin', 'ModulesCheckpaymentShop'], $domains);

        // Check integrity of translations
        $this->assertCount(16, $messages['ModulesCheckpaymentAdmin']);
        $this->assertArrayHasKey('__metadata', $messages['ModulesCheckpaymentAdmin']);
        $this->assertArrayHasKey('missing_translations', $messages['ModulesCheckpaymentAdmin']['__metadata']);
        $this->assertSame(15, $messages['ModulesCheckpaymentAdmin']['__metadata']['count']);

        $this->assertArrayHasKey('Payments by check', $messages['ModulesCheckpaymentAdmin']);
        $this->assertSame(
            [
                'default' => 'Payments by check',
                'xliff' => 'Chèque',
                'database' => null,
            ],
            $messages['ModulesCheckpaymentAdmin']['Payments by check']
        );

        $this->assertCount(20, $messages['ModulesCheckpaymentShop']);
        $this->assertSame(19, $messages['ModulesCheckpaymentShop']['__metadata']['count']);
        $this->assertArrayHasKey('Pay by check', $messages['ModulesCheckpaymentShop']);
        $this->assertSame(
            [
                'default' => 'Pay by check',
                'xliff' => 'Payer par chèque',
                'database' => null,
            ],
            $messages['ModulesCheckpaymentShop']['Pay by check']
        );
    }

    /**
     * The provider should retrieve all translations from files that
     * look like `AdminSomething` or `ModulesSomethingAdmin`
     */
    public function testGetDomainCatalogueForModule()
    {
        $providerFactory = $this->createMock(ProviderFactory::class);

        $providerFactory
            ->expects($this->any())
            ->method('build')
            ->willReturn(
                new ModulesProvider(
                    $this->databaseReader,
                    $this->getBuiltInModuleDirectory(),
                    $this->getDefaultTranslationsDirectory(),
                    $this->container->get('prestashop.translation.loader.legacy_file'),
                    $this->container->get('prestashop.translation.extractor.legacy_module'),
                    'checkpayment'
                )
            );

        $provider = new TranslationsCatalogueProvider($providerFactory);
        $messages = $provider->getDomainCatalogue(
            new ModulesType('checkPaymentShop'),
            'fr-FR',
            'ModulesCheckpaymentShop'
        );
        $this->assertIsArray($messages);

        // Check integrity of translations
        $this->assertCount(19, $messages);
        $this->assertArrayHasKey('default', $messages[0]);
        $this->assertArrayHasKey('xliff', $messages[0]);
        $this->assertArrayHasKey('database', $messages[0]);
        $this->assertArrayHasKey('tree_domain', $messages[0]);
        $this->assertSame([
            'Modules',
            'Checkpayment',
            'Shop',
        ], $messages[0]['tree_domain']);
    }

    protected function tearDown()
    {
        $langId = \Language::getIdByIso('fr', true);
        if ($langId) {
            \Db::getInstance()->execute(
                'DELETE FROM `' . _DB_PREFIX_ . 'lang` WHERE id_lang = ' . $langId
            );
        }
        self::$kernel->shutdown();
    }

    /**
     * @return string
     */
    private function getBuiltInModuleDirectory()
    {
        return __DIR__ . '/../../../../Resources/modules';
    }

    /**
     * @return string
     */
    private function getDefaultTranslationsDirectory()
    {
        return __DIR__ . '/../../../../Resources/translations';
    }
}
