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
use PrestaShopBundle\Kernel\ModuleRepositoryFactory;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Debug\Exception\ClassNotFoundException;

class AppKernel extends Kernel
{
    const VERSION = '1.7.8.0';
    const MAJOR_VERSION_STRING = '1.7';
    const MAJOR_VERSION = 17;
    const MINOR_VERSION = 8;
    const RELEASE_VERSION = 0;


    /**
     * {@inheritdoc}
     */
    public function registerBundles()
    {
        $bundles = array(
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new Symfony\Bundle\TwigBundle\TwigBundle(),
            new Symfony\Bundle\MonologBundle\MonologBundle(),
            new Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle(),
            new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle(),
            // PrestaShop Core bundle
            new PrestaShopBundle\PrestaShopBundle(),
            // PrestaShop Translation parser
            new PrestaShop\TranslationToolsBundle\TranslationToolsBundle(),
            // REST API consumer
            new Csa\Bundle\GuzzleBundle\CsaGuzzleBundle(),
            new League\Tactician\Bundle\TacticianBundle(),
            new FOS\JsRoutingBundle\FOSJsRoutingBundle(),
        );

        if (in_array($this->getEnvironment(), array('dev', 'test'), true)) {
            $bundles[] = new Symfony\Bundle\DebugBundle\DebugBundle();
            $bundles[] = new Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
            $bundles[] = new Sensio\Bundle\DistributionBundle\SensioDistributionBundle();
        }

        if ('dev' === $this->getEnvironment()) {
            $bundles[] = new Symfony\Bundle\WebServerBundle\WebServerBundle();
        }

        /* Will not work until PrestaShop is installed */
        $activeModules = $this->getActiveModules();
        if (!empty($activeModules)) {
            try {
                $this->enableComposerAutoloaderOnModules($activeModules);
            } catch (\Exception $e) {
            }
        }

        $this->loadModulesBundles($bundles);

        return $bundles;
    }

    /**
     * {@inheritdoc}
     */
    protected function getKernelParameters()
    {
        $kernelParameters = parent::getKernelParameters();

        return array_merge(
            $kernelParameters,
            array('kernel.active_modules' => $this->getActiveModules())
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getRootDir()
    {
        return __DIR__;
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheDir()
    {
        return _PS_CACHE_DIR_;
    }

    /**
     * {@inheritdoc}
     */
    public function getLogDir()
    {
        return dirname(__DIR__) . '/var/logs';
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(function (ContainerBuilder $container) {
            $container->setParameter('container.autowiring.strict_mode', true);
            $container->setParameter('container.dumper.inline_class_loader', false);
            $container->addObjectResource($this);
        });

        $loader->load($this->getRootDir() . '/config/config_' . $this->getEnvironment() . '.yml');
    }

    /**
     * Return all active modules.
     *
     * @return array list of modules names
     */
    private function getActiveModules()
    {
        $activeModules = [];
        try {
            if ($modulesRepository = ModuleRepositoryFactory::getInstance()->getRepository()) {
                $activeModules = $modulesRepository->getActiveModules();
            }
        } catch (\Exception $e) {
            //Do nothing because the modules retrieval must not block the kernel, and it won't work
            //during the installation process
        }

        return $activeModules;
    }

    /**
     * Enable auto loading of module Composer autoloader if needed.
     * Need to be done as earlier as possible in application lifecycle.
     *
     * Note: this feature is also manage in PrestaShop\PrestaShop\Adapter\ContainerBuilder
     * for non Symfony environments.
     *
     * @param array $modules the list of modules
     */
    private function enableComposerAutoloaderOnModules($modules)
    {
        foreach ($modules as $module) {
            $autoloader = __DIR__ . '/../modules/' . $module . '/vendor/autoload.php';

            if (file_exists($autoloader)) {
                include_once $autoloader;
            }
        }
    }


    /**
     * Add Bundles from active Modules to the collection.
     */
    private function loadModulesBundles(array &$bundles)
    {
        $configFile = _PS_CONFIG_DIR_ . DIRECTORY_SEPARATOR . 'module_bundles.php';
        if (! file_exists($configFile)) {
            return;
        }

        try {
            $moduleBundles = include $configFile;
        } catch(ClassNotFoundException $exception) {
            // this happens when a module is manually deleted
            // we skip loading module bundles
            return;
        }

        foreach ($moduleBundles as $moduleName => $bundleList) {
            if (! in_array($moduleName, $this->getActiveModules(), true)) {
                continue;
            }
            $bundles = array_merge($bundles, $bundleList);
        }

    }

    /**
     * Gets the application root dir.
     * Override Kernel due to the fact that we remove the composer.json in
     * downloaded package. More we are not a framework and the root directory
     * should always be the parent of this file.
     *
     * @return string The project root dir
     */
    public function getProjectDir()
    {
        return realpath(__DIR__ . '/..');
    }
}
