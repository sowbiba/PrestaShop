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

use PrestaShop\PrestaShop\Adapter\Configuration;
use PrestaShop\PrestaShop\Core\Addon\Theme\ThemeRepository;
use PrestaShop\PrestaShop\Core\Exception\FileNotFoundException;
use PrestaShopBundle\Translation\Extractor\ThemeExtractorCache;
use PrestaShopBundle\Translation\Extractor\ThemeExtractorInterface;
use PrestaShopBundle\Translation\Loader\DatabaseTranslationReader;
use PrestaShopBundle\Translation\Provider\Catalogue\TranslationCatalogueProviderInterface;
use PrestaShopBundle\Translation\Provider\CoreProvider;
use PrestaShopBundle\Translation\Provider\ThemeProvider;
use PrestaShopBundle\Translation\Provider\Type\CoreFrontType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * Test the provider of theme translations
 */
class ThemeProviderTest extends KernelTestCase
{
    /**
     * @var string
     */
    private $themesDirectory;

    /**
     * @var string
     */
    private $themeName;

    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface|null
     */
    private $container;

    /**
     * @var string
     */
    private $cacheDir;
    /**
     * @var string
     */
    private $configDir;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var CoreProvider
     */
    private $frontProvider;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|DatabaseTranslationReader
     */
    private $databaseReader;

    protected function setUp()
    {
        self::bootKernel();
        $this->container = static::$kernel->getContainer();
        $this->themeName = 'fakeThemeForTranslations';
        $this->themesDirectory = $this->container->getParameter('translations_theme_dir');
        $this->cacheDir = $this->container->getParameter('themes_translations_cache_dir');
        $this->configDir = $this->container->getParameter('kernel.cache_dir') . '/themes-config/';
        $this->filesystem = $this->container->get('filesystem');
        $this->frontProvider = $this->container->get('prestashop.translation.provider.factory.core')->build(
            new CoreFrontType()
        );
        $databaseContent = [
            [
                'lang' => 'fr-FR',
                'key' => 'Invalid name',
                'translation' => 'Traduction customisée',
                'domain' => 'fakeDomain',
                'theme' => null,
            ],
            [
                'lang' => 'fr-FR',
                'key' => 'Some made up text',
                'translation' => 'Un texte inventé',
                'domain' => 'fakeDomain',
                'theme' => 'fakeThemeForTranslations',
            ],
        ];

        $this->databaseReader = new MockDatabaseTranslationReader($databaseContent);
    }

    protected function tearDown()
    {
        // clean up
        $this->filesystem->remove([
            $this->cacheDir . '*',
            $this->configDir,
        ]);
    }

    /**
     * Test it loads a XLIFF catalogue from the theme's `translations` directory
     */
    public function testItLoadsCatalogueFromTranslationFilesInThemeDirectory()
    {
        $provider = new ThemeProvider(
            $this->frontProvider,
            $this->databaseReader,
            $this->createMock(ThemeExtractorInterface::class),
            $this->buildThemeRepository(),
            $this->filesystem,
            $this->themesDirectory,
            $this->themeName
        );

        // load catalogue from Xliff files within the theme
        $catalogue = $provider->getFileTranslatedCatalogue(TranslationCatalogueProviderInterface::DEFAULT_LOCALE);

        $this->assertInstanceOf(MessageCatalogue::class, $catalogue);

        // Check integrity of translations
        $messages = $catalogue->all();
        $this->assertArrayHasKey('ShopTheme', $messages);
        $this->assertArrayHasKey('ShopThemeCustomeraccount', $messages);

        $this->assertCount(29, $catalogue->all('ShopTheme'));
        $this->assertSame('Contact us!', $catalogue->get('Contact us', 'ShopTheme'));
    }

    /**
     * Test it extracts the default catalogue from the theme's templates
     *
     * @param bool $shouldEmptyCatalogue
     * @param array[] $expectedCatalogue
     *
     * @dataProvider provideFixturesForExtractDefaultCatalogue
     *
     * @throws FileNotFoundException
     */
    public function testItExtractsDefaultCatalogueFromThemeFiles(
        $shouldEmptyCatalogue,
        array $expectedCatalogue
    ) {
        $themeExtractorMock = $this->createMock(ThemeExtractorCache::class);

        $themeExtractorMock->expects($this->once())
            ->method('extract')
            ->willReturn($this->buildCatalogueFromMessages($expectedCatalogue));

        $provider = new ThemeProvider(
            $this->frontProvider,
            $this->databaseReader,
            $themeExtractorMock,
            $this->buildThemeRepository(),
            $this->filesystem,
            $this->themesDirectory,
            $this->themeName
        );

        // load catalogue from Xliff files within the theme
        $catalogue = $provider->getDefaultCatalogue(
            TranslationCatalogueProviderInterface::DEFAULT_LOCALE,
            $shouldEmptyCatalogue
        );

        $this->assertInstanceOf(MessageCatalogue::class, $catalogue);

        $this->assertSame($expectedCatalogue, $catalogue->all());
    }

    public function provideFixturesForExtractDefaultCatalogue(): array
    {
        $extractedMessages = [
            'SomeDomain' => [
                'Foo' => 'Foo',
                'Foo bar' => 'Foo bar',
            ],
            'SomeOtherDomain' => [
                'Barbaz' => 'Barbaz',
            ],
        ];

        $emptyCatalogue = [
            'SomeDomain' => [
                'Foo' => '',
                'Foo bar' => '',
            ],
            'SomeOtherDomain' => [
                'Barbaz' => '',
            ],
        ];

        return [
            'not empty catalogue' => [
                false,
                $extractedMessages,
            ],
            'empty catalogue' => [
                true,
                $emptyCatalogue,
            ],
        ];
    }

    public function testItLoadsCustomizedTranslationsFromDatabase()
    {
        $provider = new ThemeProvider(
            $this->frontProvider,
            $this->databaseReader,
            $this->createMock(ThemeExtractorInterface::class),
            $this->buildThemeRepository(),
            $this->filesystem,
            $this->themesDirectory,
            $this->themeName
        );

        // load catalogue from database translations
        $catalogue = $provider->getUserTranslatedCatalogue('fr-FR');

        $this->assertInstanceOf(MessageCatalogue::class, $catalogue);

        // Check integrity of translations
        $messages = $catalogue->all();
        $domains = $catalogue->getDomains();
        sort($domains);

        // verify all catalogues are loaded
        $this->assertSame([
            'fakeDomain',
        ], $domains);

        // verify that the catalogues are complete
        $this->assertCount(1, $messages['fakeDomain']);

        // verify translations
        $this->assertSame('Un texte inventé', $catalogue->get('Some made up text', 'fakeDomain'));
    }

    /**
     * @return ThemeRepository
     */
    private function buildThemeRepository(): ThemeRepository
    {
        $configuration = $this->createMock(Configuration::class);

        $configuration
            ->method('get')
            ->willReturnCallback(function ($param) {
                $configs = [
                    '_PS_ALL_THEMES_DIR_' => rtrim($this->themesDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR,
                    '_PS_CONFIG_DIR_' => $this->configDir,
                ];

                return isset($configs[$param]) ? $configs[$param] : null;
            });

        $shop = $this->container->get('prestashop.adapter.legacy.context')->getContext()->shop;

        return new ThemeRepository($configuration, $this->container->get('filesystem'), $shop);
    }

    /**
     * @param array $messages
     *
     * @return MessageCatalogue
     */
    private function buildCatalogueFromMessages(array $messages): MessageCatalogue
    {
        $catalogue = new MessageCatalogue(ThemeExtractorInterface::DEFAULT_LOCALE);
        foreach ($messages as $domain => $domainMessages) {
            $catalogue->add($domainMessages, $domain);
        }

        return $catalogue;
    }
}
