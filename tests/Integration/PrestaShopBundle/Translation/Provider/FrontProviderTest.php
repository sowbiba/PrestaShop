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

namespace Tests\Integration\PrestaShopBundle\Translation\Provider;

use PHPUnit\Framework\TestCase;
use PrestaShopBundle\Translation\Loader\DatabaseTranslationReader;
use PrestaShopBundle\Translation\Provider\FrontProvider;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * Test the provider of front translations
 */
class FrontProviderTest extends TestCase
{
    const TRANSLATIONS_DIR = __DIR__ . '/../../../../Resources/translations/';

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|DatabaseTranslationReader
     */
    private $databaseReader;

    public function setUp()
    {
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

        $this->databaseReader = new MockDatabaseTranslationReader($databaseContent);
    }

    /**
     * Test it loads a XLIFF catalogue from the locale's `translations` directory
     */
    public function testItLoadsCatalogueFromXliffFilesInLocaleDirectory()
    {
        $provider = new FrontProvider($this->databaseReader, self::TRANSLATIONS_DIR);

        // load catalogue from translations/fr-FR
        $catalogue = $provider->getFileTranslatedCatalogue('fr-FR');

        $this->assertInstanceOf(MessageCatalogue::class, $catalogue);

        // Check integrity of translations
        $messages = $catalogue->all();
        $domains = $catalogue->getDomains();
        sort($domains);

        // verify all catalogues are loaded
        $this->assertSame([
            'ShopNotificationsWarning',
        ], $domains);

        // verify that the catalogues are complete
        $this->assertCount(8, $messages['ShopNotificationsWarning']);

        // verify that translation is loaded
        $this->assertSame('Vous n\'avez reçu aucun avoir', $catalogue->get('You have not received any credit slips.', 'ShopNotificationsWarning'));
    }

    /**
     * Test it loads a default catalogue from the `translations` default directory
     */
    public function testItExtractsDefaultCatalogueFromTranslationsDefaultFiles()
    {
        $provider = new FrontProvider($this->databaseReader, self::TRANSLATIONS_DIR);

        // load catalogue from translations/default
        $catalogue = $provider->getDefaultCatalogue('fr-FR', false);

        $this->assertInstanceOf(MessageCatalogue::class, $catalogue);

        // Check integrity of translations
        $messages = $catalogue->all();
        $domains = $catalogue->getDomains();
        sort($domains);

        // verify all catalogues are loaded
        $this->assertSame([
            'ShopNotificationsWarning',
        ], $domains);

        // verify that the catalogues are complete
        $this->assertCount(8, $messages['ShopNotificationsWarning']);

        // verify that wordings are NOT translated
        $this->assertSame(
            'You have not received any credit slips.',
            $catalogue->get('You have not received any credit slips.', 'ShopNotificationsWarning')
        );

        // test get empty catalogue
        $catalogue = $provider->getDefaultCatalogue('fr-FR');

        $this->assertSame(
            '',
            $catalogue->get('You have not received any credit slips.', 'ShopNotificationsWarning')
        );
    }

    public function testItLoadsCustomizedTranslationsFromDatabase()
    {
        $provider = new FrontProvider($this->databaseReader, self::TRANSLATIONS_DIR);

        // load catalogue from database translations
        $catalogue = $provider->getUserTranslatedCatalogue('fr-FR');

        $this->assertInstanceOf(MessageCatalogue::class, $catalogue);

        // Check integrity of translations
        $messages = $catalogue->all();
        $domains = $catalogue->getDomains();
        sort($domains);

        // verify all catalogues are loaded
        $this->assertSame([
            'ShopFormsErrors',
        ], $domains);

        // verify that the catalogues are complete
        $this->assertCount(1, $messages['ShopFormsErrors']);

        // verify translations
        $this->assertSame('Traduction customisée', $catalogue->get('Invalid name', 'ShopFormsErrors'));
    }
}
