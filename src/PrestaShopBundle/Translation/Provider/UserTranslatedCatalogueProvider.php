<?php
/**
 * 2007-2020 PrestaShop SA and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
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
 * needs please refer to https://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2020 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

declare(strict_types=1);

namespace PrestaShopBundle\Translation\Provider;

use PrestaShopBundle\Translation\Loader\DatabaseTranslationLoader;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;

class UserTranslatedCatalogueProvider implements TranslationCatalogueProviderInterface, UserTranslatedCatalogueProviderInterface
{
    /**
     * @var DatabaseTranslationLoader
     */
    private $databaseLoader;

    /**
     * @var string
     */
    private $locale;

    /**
     * @var array
     */
    private $translationDomains;

    public function __construct(
        DatabaseTranslationLoader $databaseLoader,
        string $locale,
        array $translationDomains
    ) {
        $this->databaseLoader = $databaseLoader;
        $this->locale = $locale;
        $this->translationDomains = $translationDomains;
    }

    /**
     * @param string|null $themeName
     *
     * @return MessageCatalogueInterface
     */
    public function getCatalogue(?string $themeName = null): MessageCatalogueInterface
    {
        $catalogue = new MessageCatalogue($this->locale);

        foreach ($this->translationDomains as $translationDomain) {
            $domainCatalogue = $this->databaseLoader->load(
                null,
                $this->locale,
                $translationDomain,
                $themeName
            );

            if ($domainCatalogue instanceof MessageCatalogue) {
                $catalogue->addCatalogue($domainCatalogue);
            }
        }

        return $catalogue;
    }

    /**
     * @param string|null $themeName
     *
     * @return MessageCatalogueInterface
     */
    public function getUserTranslatedCatalogue(?string $themeName = null): MessageCatalogueInterface
    {
        return $this->getCatalogue($themeName);
    }
}
