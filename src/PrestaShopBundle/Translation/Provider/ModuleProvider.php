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

namespace PrestaShopBundle\Translation\Provider;

use PrestaShop\PrestaShop\Core\Exception\FileNotFoundException;
use PrestaShop\TranslationToolsBundle\Translation\Helper\DomainHelper;
use PrestaShopBundle\Translation\Loader\DatabaseTranslationLoader;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * Translation provider for a specific native module (maintained by the core team)
 * Used mainly for the display in the Translations Manager of the Back Office.
 */
class ModuleProvider implements ProviderInterface
{
    public const DEFAULT_LOCALE = 'en-US';

    /**
     * @var string the module name
     */
    private $moduleName;

    /**
     * @var DatabaseTranslationLoader
     */
    private $databaseLoader;

    /**
     * @var string the resource directory
     */
    protected $resourceDirectory;

    /**
     * @var string the Catalogue locale
     */
    protected $locale;

    /**
     * @var string the Catalogue domain
     */
    protected $domain;

    public function __construct(DatabaseTranslationLoader $databaseLoader, $resourceDirectory)
    {
        $this->databaseLoader = $databaseLoader;
        $this->resourceDirectory = $resourceDirectory;
        $this->locale = self::DEFAULT_LOCALE;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters(): array
    {
        return ['#^' . preg_quote(DomainHelper::buildModuleBaseDomain($this->moduleName)) . '([A-Z]|\.|$)#'];
    }

    /**
     * {@inheritdoc}
     */
    public function getTranslationDomains(): array
    {
        return ['^' . preg_quote(DomainHelper::buildModuleBaseDomain($this->moduleName)) . '([A-Z]|$)'];
    }

    /**
     * {@inheritdoc}
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @param string $locale
     */
    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setDomain(string $domain): self
    {
        $this->domain = $domain;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessageCatalogue(): MessageCatalogue
    {
        $messageCatalogue = $this->getDefaultCatalogue();

        $xlfCatalogue = $this->getXliffCatalogue();
        $messageCatalogue->addCatalogue($xlfCatalogue);

        $databaseCatalogue = $this->getDatabaseCatalogue();

        // Merge database catalogue to xliff catalogue
        $messageCatalogue->addCatalogue($databaseCatalogue);

        return $messageCatalogue;
    }

    /**
     * {@inheritdoc}
     *
     * @throws FileNotFoundException
     */
    public function getDefaultCatalogue(bool $empty = true): MessageCatalogue
    {
        return (new DefaultCatalogueProvider(
            [$this->resourceDirectory . DIRECTORY_SEPARATOR . 'default'],
            $this->getFilters()
        ))
            ->getCatalogue($this->locale, $empty);
    }

    /**
     * {@inheritdoc}
     *
     * @throws FileNotFoundException
     */
    public function getXliffCatalogue(): MessageCatalogue
    {
        return (new XliffCatalogueProvider(
            [$this->resourceDirectory . DIRECTORY_SEPARATOR . $this->locale],
            $this->getFilters()
        ))
            ->getCatalogue($this->locale);
    }

    /**
     * Get the Catalogue from database only.
     *
     * @param string|null $themeName
     *
     * @return MessageCatalogue A MessageCatalogue instance
     */
    public function getDatabaseCatalogue(string $themeName = null): MessageCatalogue
    {
        return (new DatabaseCatalogueProvider($this->databaseLoader, $this->getTranslationDomains()))
            ->getCatalogue($this->locale, $themeName);
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentifier(): string
    {
        return 'module';
    }

    /**
     * {@inheritdoc}
     */
    public function setModuleName(string $moduleName): self
    {
        $this->moduleName = $moduleName;

        return $this;
    }
}
