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

namespace Tests\Integration\PrestaShopBundle\Translation\Exporter;

use PHPUnit\Framework\TestCase;
use PrestaShop\PrestaShop\Core\Addon\Theme\Theme;
use PrestaShop\TranslationToolsBundle\Translation\Dumper\XliffFileDumper;
use PrestaShop\TranslationToolsBundle\Translation\Extractor\Util\Flattenizer;
use PrestaShopBundle\Translation\Exporter\ThemeExporter;
use PrestaShopBundle\Translation\Provider\Factory\ProviderFactory;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * @group sf
 */
class ThemeExporterTest extends TestCase
{
    const THEME_NAME = 'theme';

    const LOCALE = 'ab-CD';

    /**
     * @var ThemeExporter
     */
    private $themeExporter;

    private $extractorMock;

    private $providerMock;

    private $repositoryMock;

    private $dumperMock;

    private $zipManagerMock;

    private $filesystemMock;

    private $finderMock;

    protected function setUp()
    {
        $this->mockThemeExtractor();

        $this->mockThemeProvider();

        $this->mockThemeRepository();

        $this->dumperMock = new XliffFileDumper();

        $this->zipManagerMock = $this->getMockBuilder('\PrestaShopBundle\Utils\ZipManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->mockFilesystem();

        $this->mockFinder();

        $providerFactory = $this->createMock(ProviderFactory::class);

        $providerFactory
            ->expects($this->any())
            ->method('build')
            ->willReturn($this->providerMock);

        $this->themeExporter = new ThemeExporter(
            $this->extractorMock,
            $this->repositoryMock,
            $providerFactory,
            $this->dumperMock,
            $this->zipManagerMock,
            new Filesystem()
        );

        $this->themeExporter->finder = $this->finderMock;
        $this->themeExporter->exportDir = dirname(__FILE__) . '/' .
            str_repeat('../', 4) .
            'var/cache/test' .
            '/export';
    }

    public function testCreateZipArchive()
    {
        $this->themeExporter->createZipArchive(self::THEME_NAME, self::LOCALE);

        $loader = new XliffFileLoader();
        $archiveContentsParentDir = $this->themeExporter->exportDir . '/' . self::THEME_NAME . '/' . self::LOCALE;

        $finder = Finder::create();
        $catalogue = new MessageCatalogue(self::LOCALE, []);

        foreach ($finder->in($archiveContentsParentDir)->files() as $file) {
            $catalogue->addCatalogue(
                $loader->load(
                    $file->getPathname(),
                    self::LOCALE,
                    $file->getBasename('.' . $file->getExtension())
                )
            );
        }

        $messages = $catalogue->all();
        $domain = 'ShopActions.' . self::LOCALE;
        $this->assertArrayHasKey($domain, $messages);

        $this->assertArrayHasKey('Add Product', $messages[$domain]);
        $this->assertArrayHasKey('Edit Product', $messages[$domain]);
        $this->assertArrayHasKey('Delete Product', $messages[$domain]);

        $this->assertArrayHasKey('Override Me Twice', $messages[$domain]);
        $this->assertSame('Add Product', $messages[$domain]['Add Product']);
        $this->assertSame('Delete Product', $messages[$domain]['Delete Product']);
        $this->assertSame('Overridden Twice', $messages[$domain]['Override Me Twice']);
    }

    protected function mockThemeExtractor()
    {
        $this->extractorMock = $this->getMockBuilder('\PrestaShopBundle\Translation\Extractor\ThemeExtractorCache')
            ->disableOriginalConstructor()
            ->getMock();

        $cachedFilesPath = implode(DIRECTORY_SEPARATOR, [sys_get_temp_dir(), 'ThemeExporterTest', 'cache']);
        $this->extractorMock->method('getStorageFilesPath')
            ->willReturn($cachedFilesPath);

        $temporaryFilesPath = implode(DIRECTORY_SEPARATOR, [sys_get_temp_dir(), 'ThemeExporterTest', 'temp']);
        $this->extractorMock->method('getTemporaryFilesPath')
            ->willReturn($temporaryFilesPath);

        $catalogue = new MessageCatalogue(self::LOCALE);
        $wordings = [
            'ShopSomeDomain' => [
                'Some wording' => 'Some wording',
                'Some other wording' => 'Some other wording',
            ],
            'ShopSomethingElse' => [
                'Foo' => 'Foo',
                'Bar' => 'Bar',
            ],
        ];
        foreach ($wordings as $domain => $messages) {
            $catalogue->add($messages, $domain);

            foreach ($messages as $key => $message) {
                $catalogue->setMetadata($key, ['line' => rand(1, 100), 'file' => 'filename'], $domain);
            }
        }

        $this->extractorMock->method('extract')
            ->willReturn($catalogue);
    }

    protected function mockThemeRepository()
    {
        $this->repositoryMock = $this->getMockBuilder('\PrestaShop\PrestaShop\Core\Addon\Theme\ThemeRepository')
            ->disableOriginalConstructor()
            ->getMock();

        $this->repositoryMock->method('getInstanceByName')
            ->willReturn(new Theme([
                'directory' => '',
                'name' => self::THEME_NAME,
            ]));
    }

    protected function mockFilesystem()
    {
        $this->filesystemMock = $this->getMockBuilder('\Symfony\Component\Filesystem\Filesystem')
            ->disableOriginalConstructor()
            ->getMock();

        $this->filesystemMock->method('mkdir')
            ->willReturn(null);

        Flattenizer::$filesystem = $this->filesystemMock;
    }

    protected function mockFinder()
    {
        $this->finderMock = $this->getMockBuilder('\Symfony\Component\Finder\Finder')
            ->disableOriginalConstructor()
            ->getMock();

        $this->finderMock->method('in')
            ->willReturn($this->finderMock);

        $this->finderMock->method('files')
            ->willReturn([]);

        Flattenizer::$finder = $this->finderMock;
    }

    protected function mockThemeProvider()
    {
        $this->providerMock = $this->getMockBuilder('\PrestaShopBundle\Translation\Provider\ThemeProvider')
            ->disableOriginalConstructor()
            ->getMock();

        $this->providerMock->method('getDefaultCatalogue')
            ->willReturn(new MessageCatalogue(
                self::LOCALE,
                [
                    'ShopActions.' . self::LOCALE => [
                        'Add Product' => 'Add',
                        'Override Me' => '',
                        'Override Me Twice' => '',
                    ],
                ]
            ));

        $this->providerMock->method('getFileTranslatedCatalogue')
            ->willReturn(new MessageCatalogue(
                self::LOCALE,
                [
                    'ShopActions.' . self::LOCALE => [
                        'Edit Product' => 'Edit',
                        'Override Me' => 'Overridden',
                        'Override Me Twice' => 'Overridden Once',
                    ],
                ]
            ));

        $this->providerMock->method('getUserTranslatedCatalogue')
            ->willReturn(new MessageCatalogue(
                self::LOCALE,
                [
                    'ShopActions' => [
                        'Delete Product' => 'Delete',
                        'Override Me Twice' => 'Overridden Twice',
                    ],
                ]
            ));
    }
}
