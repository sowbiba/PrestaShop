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

namespace PrestaShopBundle\Translation\Loader;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PrestaShopBundle\Entity\Lang;
use PrestaShopBundle\Entity\Repository\LangRepository;
use PrestaShopBundle\Entity\Repository\TranslationRepository;
use PrestaShopBundle\Entity\Translation;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * Loads translations from database
 */
class DatabaseTranslationReader
{
    /** @var EntityManagerInterface */
    protected $entityManager;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Loads all user translations according to search parameters
     *
     * @param string $locale Translation language
     * @param string $domainSearch Regex for domain pattern search
     * @param string|null $theme [default=null] Theme name
     *
     * @return MessageCatalogue
     */
    public function load(string $locale, string $domainSearch, ?string $theme = null): MessageCatalogue
    {
        static $langs = [];
        $catalogue = new MessageCatalogue($locale);

        if (!array_key_exists($locale, $langs)) {
            /** @var LangRepository $langRepository */
            $langRepository = $this->entityManager->getRepository('PrestaShopBundle:Lang');
            $langs[$locale] = $langRepository->getOneByLocale($locale);
        }

        /** @var TranslationRepository $translationRepository */
        $translationRepository = $this->entityManager
            ->getRepository('PrestaShopBundle:Translation');

        $queryBuilder = $translationRepository->createQueryBuilder('t');

        $this->addLangConstraint($queryBuilder, $langs[$locale]);

        $this->addThemeConstraint($queryBuilder, $theme);

        $this->addDomainConstraint($queryBuilder, $domainSearch);

        $translations = $queryBuilder
            ->getQuery()
            ->getResult();

        /** @var Translation $translation */
        foreach ($translations as $translation) {
            $catalogue->set($translation->getKey(), $translation->getTranslation(), $translation->getDomain());
        }

        return $catalogue;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param Lang $currentLang
     */
    private function addLangConstraint(QueryBuilder $queryBuilder, Lang $currentLang)
    {
        $queryBuilder->andWhere('t.lang =:lang')
            ->setParameter('lang', $currentLang);
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param string|null $theme
     */
    private function addThemeConstraint(QueryBuilder $queryBuilder, $theme)
    {
        if (null === $theme) {
            $queryBuilder->andWhere('t.theme IS NULL');
        } else {
            $queryBuilder
                ->andWhere('t.theme = :theme')
                ->setParameter('theme', $theme);
        }
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param string $domain
     */
    private function addDomainConstraint(QueryBuilder $queryBuilder, $domain)
    {
        if ($domain !== '*') {
            $queryBuilder->andWhere('REGEXP(t.domain, :domain) = true')
                ->setParameter('domain', $domain);
        }
    }
}
