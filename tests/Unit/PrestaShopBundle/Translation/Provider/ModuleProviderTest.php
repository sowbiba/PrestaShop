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

namespace Tests\Unit\PrestaShopBundle\Translation\Provider;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PrestaShopBundle\Translation\Extractor\LegacyModuleExtractorInterface;
use PrestaShopBundle\Translation\Loader\DatabaseTranslationReader;
use PrestaShopBundle\Translation\Provider\Catalogue\DefaultCatalogueProvider;
use PrestaShopBundle\Translation\Provider\ModulesProvider;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;

class ModuleProviderTest extends TestCase
{
    /**
     * @var string
     */
    private static $tempDir;

    private static $wordings = [
        'ModulesModulenameSomeDomain' => [
            'Some wording' => 'Some wording',
            'Some other wording' => 'Some other wording',
        ],
        'ModulesModulenameSomethingElse' => [
            'Foo' => 'Foo',
            'Bar' => 'Bar',
        ],
    ];

    private static $emptyWordings = [
        'ModulesModulenameSomeDomain' => [
            'Some wording' => '',
            'Some other wording' => '',
        ],
        'ModulesModulenameSomethingElse' => [
            'Foo' => '',
            'Bar' => '',
        ],
    ];

    /**
     * @var ModulesProvider
     */
    private $modulesProvider;

    public function setUp()
    {
        /** @var MockObject|DatabaseTranslationReader $databaseReader */
        $databaseReader = $this->createMock(DatabaseTranslationReader::class);
        /** @var MockObject|LoaderInterface $legacyFileLoader */
        $legacyFileLoader = $this->createMock(LoaderInterface::class);

        $catalogue = new MessageCatalogue(DefaultCatalogueProvider::DEFAULT_LOCALE);
        foreach (self::$wordings as $domain => $messages) {
            $catalogue->add($messages, $domain);
        }
        /** @var MockObject|LegacyModuleExtractorInterface $legacyModuleExtractor */
        $legacyModuleExtractor = $this->createMock(LegacyModuleExtractorInterface::class);
        $legacyModuleExtractor
            ->method('extract')
            ->willReturn($catalogue);

        self::$tempDir = implode(DIRECTORY_SEPARATOR, [sys_get_temp_dir(), 'ModuleProviderTest']);
        if (!is_dir(self::$tempDir)) {
            mkdir(self::$tempDir);
        }

        $this->modulesProvider = (new ModulesProvider(
            $databaseReader,
            self::$tempDir,
            self::$tempDir,
            $legacyFileLoader,
            $legacyModuleExtractor,
            'moduleName'
        ));
    }

    public static function setUpBeforeClass()
    {
        $catalogue = new MessageCatalogue(DefaultCatalogueProvider::DEFAULT_LOCALE);
        foreach (self::$wordings as $domain => $messages) {
            $catalogue->add($messages, $domain);
        }

        self::$tempDir = implode(DIRECTORY_SEPARATOR, [sys_get_temp_dir(), 'ModuleProviderTest']);
        if (!is_dir(self::$tempDir)) {
            mkdir(self::$tempDir);
        }
        if (!is_dir(self::$tempDir . DIRECTORY_SEPARATOR . DefaultCatalogueProvider::DEFAULT_LOCALE)) {
            mkdir(self::$tempDir . DIRECTORY_SEPARATOR . DefaultCatalogueProvider::DEFAULT_LOCALE);
        }
        // default translation directory
        (new XliffFileDumper())->dump($catalogue, [
            'path' => implode(DIRECTORY_SEPARATOR, [self::$tempDir, DefaultCatalogueProvider::DEFAULT_LOCALE]) . DIRECTORY_SEPARATOR,
        ]);
        // module built-in translation directory
        (new XliffFileDumper())->dump($catalogue, [
            'path' => implode(
                    DIRECTORY_SEPARATOR,
                    [self::$tempDir, 'moduleName', 'translations', DefaultCatalogueProvider::DEFAULT_LOCALE]
                ) . DIRECTORY_SEPARATOR,
        ]);
    }

    public function testGetDefaultCatalogue()
    {
        $catalogue = $this->modulesProvider->getDefaultCatalogue(DefaultCatalogueProvider::DEFAULT_LOCALE);
        $catalogueAsArray = $catalogue->all();
        ksort($catalogueAsArray);
        $this->assertSame(self::$wordings, $catalogueAsArray);

        $catalogue = $this->modulesProvider->getDefaultCatalogue(
            DefaultCatalogueProvider::DEFAULT_LOCALE,
            true
        );
        $this->assertSame(self::$wordings, $catalogue->all());

        $catalogue = $this->modulesProvider->getDefaultCatalogue('fr-FR', true);
        $this->assertSame(self::$emptyWordings, $catalogue->all());
    }

    public function testGetFilesystemCatalogue()
    {
        $catalogue = $this->modulesProvider->getFileTranslatedCatalogue(DefaultCatalogueProvider::DEFAULT_LOCALE);
        $this->assertSame(self::$wordings, $catalogue->all());
    }
}
