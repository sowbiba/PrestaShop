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
use PrestaShopBundle\Translation\Factory\ProviderNotFoundException;
use PrestaShopBundle\Translation\Loader\DatabaseTranslationLoader;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;

class CoreProvider implements ProviderInterface
{
    const DEFAULT_LOCALE = 'en-US';

    /**
     * @var DatabaseTranslationLoader
     */
    private $databaseTranslationLoader;

    /**
     * @var string
     */
    private $resourceDirectory;
    /**
     * @var string
     */
    protected $locale;
    /**
     * @var string
     */
    private $identifier;

    /**
     * @param DatabaseTranslationLoader $databaseTranslationLoader
     * @param string $resourceDirectory
     * @param string $identifier
     */
    public function __construct(
        DatabaseTranslationLoader $databaseTranslationLoader,
        string $resourceDirectory,
        string $identifier
    ) {
        $this->locale = self::DEFAULT_LOCALE;

        $this->databaseTranslationLoader = $databaseTranslationLoader;
        $this->resourceDirectory = $resourceDirectory;
        $this->identifier = $identifier;
    }

    /**
     * @param string $locale
     */
    public function setLocale(string $locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultCatalogue(bool $empty = true): MessageCatalogueInterface
    {
        $defaultCatalogue = new MessageCatalogue($this->locale);

        foreach ($this->getFilenameFilters() as $filter) {
            $filteredCatalogue = $this->getCatalogueFromPaths(
                [$this->resourceDirectory . DIRECTORY_SEPARATOR . 'default'],
                $this->locale,
                $filter
            );
            $defaultCatalogue->addCatalogue($filteredCatalogue);
        }

        if ($empty && $this->locale !== self::DEFAULT_LOCALE) {
            $defaultCatalogue = $this->emptyCatalogue($defaultCatalogue);
        }

        return $defaultCatalogue;
    }

    /**
     * {@inheritdoc}
     */
    public function getXliffCatalogue(): MessageCatalogueInterface
    {
        $xlfCatalogue = new MessageCatalogue($this->locale);

        foreach ($this->getFilenameFilters() as $filter) {
            $filteredCatalogue = $this->getCatalogueFromPaths(
                [$this->resourceDirectory . DIRECTORY_SEPARATOR . $this->locale],
                $this->locale,
                $filter
            );
            $xlfCatalogue->addCatalogue($filteredCatalogue);
        }

        return $xlfCatalogue;
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabaseCatalogue($themeName = null): MessageCatalogueInterface
    {
        $databaseCatalogue = new MessageCatalogue($this->locale);

        foreach ($this->getTranslationDomains() as $translationDomain) {
            $domainCatalogue = $this->databaseTranslationLoader->load(
                null,
                $this->locale,
                $translationDomain,
                $themeName
            );

            if ($domainCatalogue instanceof MessageCatalogue) {
                $databaseCatalogue->addCatalogue($domainCatalogue);
            }
        }

        return $databaseCatalogue;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getMessageCatalogue(): MessageCatalogueInterface
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
     * @param array $paths a list of paths when we can look for translations
     * @param string $locale the Symfony (not the PrestaShop one) locale
     * @param string|null $pattern a regular expression
     *
     * @return MessageCatalogue
     *
     * @throws FileNotFoundException
     */
    private function getCatalogueFromPaths($paths, $locale, $pattern = null)
    {
        return (new TranslationFinder())->getCatalogueFromPaths($paths, $locale, $pattern);
    }

    /**
     * Empties out the catalogue by removing translations but leaving keys
     *
     * @param MessageCatalogueInterface $messageCatalogue
     *
     * @return MessageCatalogueInterface Empty the catalogue
     */
    private function emptyCatalogue(MessageCatalogueInterface $messageCatalogue)
    {
        foreach ($messageCatalogue->all() as $domain => $messages) {
            foreach (array_keys($messages) as $translationKey) {
                $messageCatalogue->set($translationKey, '', $domain);
            }
        }

        return $messageCatalogue;
    }

    /**
     * @return string[]
     *
     * @throws ProviderNotFoundException
     */
    protected function getTranslationDomains(): array
    {
        switch ($this->identifier) {
            case 'back':
                return [
                    '^Admin[A-Z]',
                    '^Modules[A-Z](.*)Admin',
                ];
            case 'front':
                return [
                    '^Shop*',
                    '^Modules(.*)Shop',
                ];
            case 'mails_body':
                return ['EmailsBody*'];
            case 'mails':
                return ['EmailsSubject*'];
            case 'modules':
                return ['^Modules[A-Z]'];
            case 'others':
                return ['^messages*'];
            default:
                throw new ProviderNotFoundException($this->identifier);
        }
    }

    /**
     * @return string[]
     *
     * @throws ProviderNotFoundException
     */
    protected function getFilenameFilters(): array
    {
        switch ($this->identifier) {
            case 'back':
                return [
                    '#^Admin[A-Z]#',
                    '#^Modules[A-Z](.*)Admin#',
                ];
            case 'front':
                return [
                    '#^Shop*#',
                    '#^Modules(.*)Shop#',
                ];
            case 'mails_body':
                return ['#EmailsBody*#'];
            case 'mails':
                return ['#EmailsSubject*#'];
            case 'modules':
                return ['#^Modules[A-Z]#'];
            case 'others':
                return ['#^messages*#'];
            default:
                throw new ProviderNotFoundException($this->identifier);
        }
    }
}
