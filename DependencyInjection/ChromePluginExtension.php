<?php

namespace KimaiPlugin\ChromePluginBundle\DependencyInjection;

use App\Plugin\AbstractPluginExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\Yaml\Parser;

class ChromePluginExtension extends AbstractPluginExtension {

  public function load(array $configs, ContainerBuilder $container) {
    try {
      $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
      $loader->load('services.yaml');
    } catch (\Exception $e) {
      echo '[ChromePluginExtension] invalid services config found: ' . $e->getMessage();
    }
  }
}


/*
class ChromePluginExtension extends AbstractPluginExtension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }
}
*/