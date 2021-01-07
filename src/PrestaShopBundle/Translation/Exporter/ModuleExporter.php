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

namespace PrestaShopBundle\Translation\Exporter;

use PrestaShop\PrestaShop\Core\Exception\FileNotFoundException;
use PrestaShop\TranslationToolsBundle\Translation\Dumper\PhpDumper;
use PrestaShop\TranslationToolsBundle\Translation\Dumper\XliffFileDumper;
use PrestaShop\TranslationToolsBundle\Translation\Extractor\Util\Flattenizer;
use PrestaShopBundle\Exception\NotImplementedException;
use PrestaShopBundle\Translation\Extractor\LegacyModuleExtractorInterface;
use PrestaShopBundle\Translation\Provider\Factory\ProviderFactory;
use PrestaShopBundle\Translation\Provider\Type\ModulesType;
use PrestaShopBundle\Utils\ZipManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;

/**
 * Exports a module's translations
 */
class ModuleExporter
{
    /**
     * @var ZipManager the zip manager
     */
    private $zipManager;

    /**
     * @var XliffFileDumper the Xliff dumper
     */
    private $dumper;

    /**
     * @var Filesystem the Filesystem
     */
    private $filesystem;

    /**
     * @var string the export directory path
     */
    public $exportDir;
    /**
     * @var ProviderFactory
     */
    private $providerFactory;
    /**
     * @var string
     */
    private $cacheDir;
    /**
     * @var PhpDumper
     */
    private $phpDumper;
    /**
     * @var LegacyModuleExtractorInterface
     */
    private $legacyModuleExtractor;

    public function __construct(
        LegacyModuleExtractorInterface $legacyModuleExtractor,
        ProviderFactory $providerFactory,
        XliffFileDumper $dumper,
        PhpDumper $phpDumper,
        ZipManager $zipManager,
        Filesystem $filesystem,
        string $cacheDir
    ) {
        $this->dumper = $dumper;
        $this->zipManager = $zipManager;
        $this->filesystem = $filesystem;
        $this->providerFactory = $providerFactory;
        $this->cacheDir = $cacheDir;
        $this->phpDumper = $phpDumper;
        $this->legacyModuleExtractor = $legacyModuleExtractor;
    }

    /**
     * Extracts the module's translations in a particular locale and bundles them in a zip file
     *
     * @param string $moduleName Module name
     * @param string $locale Locale for the exported catalogue
     * @param string|false $rootDir Path to use as root for the translation metadata
     *
     * @return string Full path to the zip file
     */
    public function createZipArchive($moduleName, $locale, $rootDir = false)
    {
        $archiveParentDirectory = $this->exportCatalogues($moduleName, $locale, $rootDir);
        $zipFilename = $this->makeZipFilename($moduleName, $locale);
        $this->zipManager->createArchive($zipFilename, $archiveParentDirectory);

        return $zipFilename;
    }

    /**
     * Extracts the module's translations in a particular locale as XLIFF files in a temporary directory
     *
     * @param string $moduleName Module name
     * @param string $locale Locale for the exported catalogue
     * @param string|false $rootDir Path to use as root for the translation metadata
     *
     * @return string The directory where the files have been exported
     *
     * @throws NotImplementedException
     */
    public function exportCatalogues(string $moduleName, string $locale, $rootDir = false): string
    {
        $catalogue = $this->getCatalogue($moduleName, $locale);

        // $storageFilesPath = var/cache/test/ps_banner-tmp
        $storageFilesPath = $this->getStorageFilesPath($moduleName);
        // $temporaryFilesPath = cacheDir/moduleName
        $temporaryFilesPath = $this->getTemporaryFilesPath($moduleName);

        $this->filesystem->remove($temporaryFilesPath);

        // $tmpExtractPath = cacheDir/moduleName/LOCALE
        $tmpExtractPath = $temporaryFilesPath . DIRECTORY_SEPARATOR . $locale;

        $this->filesystem->mkdir($tmpExtractPath);

        $this->dumper->dump($catalogue, [
            'path' => $temporaryFilesPath,
            'default_locale' => $locale,
            'root_dir' => $rootDir,
        ]);

        // The files in cacheDir/moduleName/LOCALE are flatten into cacheDir/export/moduleName/LOCALE/
        // so that the translation files structure is DomainoneDomaintwoFinaleSheet.LOCALE.xlf
        Flattenizer::flatten(
            $tmpExtractPath,
            $storageFilesPath . DIRECTORY_SEPARATOR . $locale,
            $locale
        );

        $this->phpDumper->dump($catalogue, [
            'path' => $storageFilesPath,
            'default_locale' => $locale,
            'root_dir' => $rootDir,
        ]);

        // $archiveDirectory = cacheDir/export/moduleName/LOCALE/
        $archiveDirectory = $this->getExportDir($moduleName);
        if (!$this->filesystem->exists($archiveDirectory)) {
            $this->filesystem->mkdir($archiveDirectory);
        }

        // Clean up previously exported archives
        $this->filesystem->remove($archiveDirectory);

        // Build final files structure
        $archiveXlfFilesPath = implode(DIRECTORY_SEPARATOR, [
            $archiveDirectory,
            'translations',
            $locale,
        ]);
        $tmpXlfFilesPath = $storageFilesPath . DIRECTORY_SEPARATOR . $locale;

        $archiveLegacyFilesPath = $archiveDirectory . DIRECTORY_SEPARATOR . 'translations';
        $tmpLegacyFilesPath = implode(DIRECTORY_SEPARATOR, [
            $storageFilesPath,
            'modules',
            $moduleName,
            'translations',
        ]);

        $this->filesystem->mkdir($archiveXlfFilesPath);

        $this->filesystem->mirror($tmpXlfFilesPath, $archiveXlfFilesPath);
        $this->filesystem->mirror($tmpLegacyFilesPath, $archiveLegacyFilesPath);

        return $archiveDirectory;
    }

    /**
     * @param string $exportDir
     */
    public function setExportDir(string $exportDir): void
    {
        $this->exportDir = str_replace('/export', DIRECTORY_SEPARATOR . 'export', $exportDir);
    }

    /**
     * @param string $moduleName
     */
    public function cleanArtifacts(string $moduleName): void
    {
        $this->filesystem->remove($this->getStorageFilesPath($moduleName));
        $this->filesystem->remove($this->getTemporaryFilesPath($moduleName));
    }

    /**
     * @param string $moduleName
     *
     * @return string
     */
    protected function getExportDir(string $moduleName): string
    {
        return $this->exportDir . DIRECTORY_SEPARATOR . $moduleName;
    }

    /**
     * @param string $moduleName
     * @param string $locale
     *
     * @return string
     */
    protected function makeZipFilename(string $moduleName, string $locale): string
    {
        if (!file_exists($this->exportDir)) {
            mkdir($this->exportDir);
        }

        $zipFilenameParts = [
            $this->exportDir,
            $moduleName,
            //            $locale,
            $moduleName . '.' . $locale . '.zip',
        ];

        return implode(DIRECTORY_SEPARATOR, $zipFilenameParts);
    }

    /**
     * @param MessageCatalogueInterface $catalogue
     */
    protected function updateCatalogueMetadata(MessageCatalogueInterface $catalogue): void
    {
        foreach ($catalogue->all() as $domain => $messages) {
            $this->ensureCatalogueHasRequiredMetadata($catalogue, $messages, $domain);
        }
    }

    /**
     * @param MessageCatalogue $catalogue
     * @param array $messages
     * @param string $domain
     */
    protected function ensureCatalogueHasRequiredMetadata(
        MessageCatalogue $catalogue,
        array $messages,
        string $domain
    ): void {
        foreach (array_keys($messages) as $id) {
            $metadata = $catalogue->getMetadata($id, $domain);
            if ($this->shouldAddFileMetadata($metadata)) {
                $catalogue->setMetadata($id, $this->parseMetadataNotes($metadata), $domain);
            }
        }
    }

    /**
     * @param array|null $metadata
     *
     * @return bool
     */
    protected function metadataContainNotes(array $metadata = null): bool
    {
        return null !== $metadata && array_key_exists('notes', $metadata) && is_array($metadata['notes']) &&
            array_key_exists(0, $metadata['notes']) && is_array($metadata['notes'][0]) &&
            array_key_exists('content', $metadata['notes'][0]);
    }

    /**
     * @param array|null $metadata
     *
     * @return bool
     */
    protected function shouldAddFileMetadata(array $metadata = null): bool
    {
        return null === $metadata || !array_key_exists('file', $metadata);
    }

    /**
     * @param array|null $metadata
     *
     * @return array
     */
    protected function parseMetadataNotes(array $metadata = null): array
    {
        $defaultMetadata = ['file' => '', 'line' => ''];

        if (!$this->metadataContainNotes($metadata)) {
            return $defaultMetadata;
        }

        $notes = $metadata['notes'][0]['content'];
        if (1 !== preg_match('/(?<file>\S+):(?<line>\S+)/m', $notes, $matches)) {
            return $defaultMetadata;
        }

        return [
            'file' => $matches['file'],
            'line' => $matches['line'],
        ];
    }

    /**
     * Returns the path to the directory where default translations are stored in cache
     *
     * @param string $moduleName
     *
     * @return string
     */
    private function getStorageFilesPath(string $moduleName): string
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . $moduleName . '-tmp';
    }

    /**
     * Returns the path to the directory where default translations are stored in cache
     *
     * @param string $moduleName
     *
     * @return string
     */
    private function getTemporaryFilesPath(string $moduleName): string
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . $moduleName;
    }

    /**
     * @param string $moduleName
     * @param string $locale
     *
     * @return MessageCatalogueInterface
     *
     * @throws NotImplementedException
     */
    private function getCatalogue(string $moduleName, string $locale): MessageCatalogueInterface
    {
        $mergedTranslations = $this->legacyModuleExtractor
            ->extract($moduleName, $locale);

        $moduleProvider = $this->providerFactory->build(new ModulesType($moduleName));
        $databaseCatalogue = $moduleProvider->getUserTranslatedCatalogue($locale);
        try {
            $moduleCatalogue = $moduleProvider->getFileTranslatedCatalogue($locale);
        } catch (FileNotFoundException $exception) {
            // if the module doesn't have translation files (eg. the default module)
            $moduleCatalogue = new MessageCatalogue($locale);
        }

        $mergedTranslations->addCatalogue($moduleCatalogue);
        $mergedTranslations->addCatalogue($databaseCatalogue);

        $this->updateCatalogueMetadata($mergedTranslations);

        return $mergedTranslations;
    }
}
