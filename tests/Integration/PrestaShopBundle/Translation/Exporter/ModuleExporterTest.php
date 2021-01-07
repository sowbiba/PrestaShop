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

use PrestaShop\TranslationToolsBundle\Translation\Dumper\PhpDumper;
use PrestaShop\TranslationToolsBundle\Translation\Dumper\XliffFileDumper;
use PrestaShop\TranslationToolsBundle\Translation\Extractor\Util\Flattenizer;
use PrestaShopBundle\Translation\Exporter\ModuleExporter;
use PrestaShopBundle\Translation\Extractor\LegacyModuleExtractor;
use PrestaShopBundle\Translation\Provider\Factory\ProviderFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * @group sf
 */
class ModuleExporterTest extends KernelTestCase
{
    const MODULE_NAME = 'ps_banner';

    const LOCALE = 'ab-CD';

    /**
     * @var ModuleExporter
     */
    private $moduleExporter;

    private $providerMock;

    private $dumperMock;

    private $phpDumperMock;

    private $zipManagerMock;

    private $filesystemMock;

    protected function setUp()
    {
        self::bootKernel();

        $container = self::$kernel->getContainer();

        $phpExtractor = $container->get('prestashop.translation.extractor.php');
        $smartyExtractor = $container->get('prestashop.translation.extractor.smarty.legacy');
        $twigExtractor = $container->get('prestashop.translation.extractor.twig');
        $modulesDirectory = $container->getParameter('translations_modules_dir');

        $extractor = new LegacyModuleExtractor(
            $phpExtractor,
            $smartyExtractor,
            $twigExtractor,
            $modulesDirectory
        );

        $this->mockModuleProvider();

        $this->dumperMock = new XliffFileDumper();
        $this->phpDumperMock = new PhpDumper();

        $this->zipManagerMock = $this->getMockBuilder('\PrestaShopBundle\Utils\ZipManager')
            ->disableOriginalConstructor()
            ->getMock();

        $providerFactory = $this->createMock(ProviderFactory::class);

        $providerFactory
            ->expects($this->any())
            ->method('build')
            ->willReturn($this->providerMock);

        $cacheDir = dirname(__FILE__) . '/' . str_repeat('../', 5) . 'var/cache/test';

        $this->moduleExporter = new ModuleExporter(
            $extractor,
            $providerFactory,
            $this->dumperMock,
            $this->phpDumperMock,
            $this->zipManagerMock,
            new Filesystem(),
            $cacheDir
        );

        $this->moduleExporter->exportDir = $cacheDir . '/export';
    }

    public function testCreateZipArchive()
    {
        $this->moduleExporter->createZipArchive(self::MODULE_NAME, self::LOCALE);

        $loader = new XliffFileLoader();
        $archiveContentsParentDir = $this->moduleExporter->exportDir . '/' . self::MODULE_NAME . '/' . 'translations' . '/' . self::LOCALE;

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
        $domain = 'Admin.' . self::LOCALE;
        $this->assertArrayHasKey($domain, $messages);

        $this->assertArrayHasKey('Delete Product', $messages[$domain]);
        $this->assertArrayHasKey('Override Me Twice', $messages[$domain]);

        $this->assertSame('Delete', $messages[$domain]['Delete Product']);
        $this->assertSame('Override Me Twice', $messages[$domain]['Override Me Twice']);
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

    protected function mockModuleProvider()
    {
        $this->providerMock = $this->getMockBuilder('\PrestaShopBundle\Translation\Provider\ModulesProvider')
            ->disableOriginalConstructor()
            ->getMock();

        $this->providerMock->method('getFileTranslatedCatalogue')
            ->willReturn(new MessageCatalogue(
                self::LOCALE,
                [
                    'Admin' => [
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
                    'Admin' => [
                        'Banner' => 'Bannière',
                        'Delete Product' => 'Delete',
                        'Override Me Twice' => 'Overridden Once',
                    ],
                    'Modules.Banner.Admin' => [
                        'Banner' => 'Bannière',
                    ],
                ]
            ));
    }
}
