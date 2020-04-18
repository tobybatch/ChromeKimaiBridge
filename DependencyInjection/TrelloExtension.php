<?php

namespace KimaiPlugin\TrelloBundle\DependencyInjection;

use App\Plugin\AbstractPluginExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\Yaml\Parser;

class TrelloExtension extends AbstractPluginExtension {

  public function load(array $configs, ContainerBuilder $container) {
    try {
      $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
      $loader->load('services.yaml');
    } catch (\Exception $e) {
      echo '[TrelloExtension] invalid services config found: ' . $e->getMessage();
    }
  }
}


/*
class TrelloExtension extends AbstractPluginExtension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }
}
*/