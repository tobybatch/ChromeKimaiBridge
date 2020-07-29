<?php
declare(strict_types=1);

namespace KimaiPlugin\ChromePluginBundle\DependencyInjection;

use App\Plugin\AbstractPluginExtension;
use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;

class ChromePluginExtension extends AbstractPluginExtension
{

    public function load(array $configs, ContainerBuilder $container)
    {
        try {
            $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
            $loader->load('services.yaml');
        } catch (Exception $e) {
            echo '[ChromePluginExtension] invalid services config found: ' . $e->getMessage();
        }
    }
}
