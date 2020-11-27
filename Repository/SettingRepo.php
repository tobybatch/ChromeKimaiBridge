<?php

declare(strict_types=1);

namespace KimaiPlugin\ChromePluginBundle\Repository;

use KimaiPlugin\ChromePluginBundle\Entity\SettingEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class ChromeSettingRepository
 *
 * @method SettingEntity findOneBy(int $id) Fetch one entity by id
 */
class SettingRepo
{
    private Filesystem $filesystem;

    /**
     * @method findByHostname
     */

    /**
     * @var string
     */
    private string $storage;

    /**
     * ChromeSettingRepository constructor.
     * @param ContainerInterface $container
     * @param string $dataDirectory
     */
    public function __construct(ContainerInterface $container, string $dataDirectory)
    {
        $this->filesystem = new Filesystem();

        $this->storage = $dataDirectory . '/chromeSetting/' . $container->getParameter('kernel.environment');
        if (!$this->filesystem->exists($this->storage)) {
            $this->filesystem->mkdir($this->storage, 0775);

            $chrome_setting = new SettingEntity();
            $chrome_setting->setHostname("github.com");
            $chrome_setting->setProjectRegex("(?<=github.com\/)([a-zA-Z-]+)");
            $chrome_setting->setIssueRegex("\d+$");
            $this->save($chrome_setting);
        }
    }

    public function getStorage() {
        return $this->storage;
    }

    /**
     * @param SettingEntity $setting
     */
    public function save(SettingEntity $setting)
    {
        $filename = $this->makeFilename($setting->getHostname());
        file_put_contents($filename, $setting->toJson());
    }

    /**
     * @return array
     */
    public function findAll(): array
    {
        // Symfony does not have a ls dir function
        $file_list = glob($this->storage . '/*.json');
        $settings = [];
        foreach ($file_list as $filename) {
            $settings[] = $this->findByHostname(basename($filename, ".json"));
        }
        return $settings;
    }

    /**
     * @param string $hostname
     * @return SettingEntity|null
     */
    public function findByHostname(string $hostname): ?SettingEntity
    {
        $filename = $this->makeFilename($hostname);
        if ($this->filesystem->exists($filename)) {
            return SettingEntity::fromJson(file_get_contents($filename));
        }
        return null;
    }

    /**
     * @param SettingEntity $setting
     * @return bool
     */
    public function remove(SettingEntity $setting): bool
    {
        return $this->removeByHost($setting->getHostname());
    }

    public function removeAll()
    {
        // Symfony does not have a ls dir function
        $file_list = glob($this->storage . '/*.json');
        foreach ($file_list as $filename) {
            $this->filesystem->remove($filename);
        }
    }

    public function removeByHost($hostname)
    {
        $filename = $this->makeFilename($hostname);
        if ($this->filesystem->exists($filename)) {
            $this->filesystem->remove($filename);
            return true;
        } else {
            return false;
        }
    }

    public function getPredefinedGitHub()
    {
        $settingsEntity = new SettingEntity();
        $settingsEntity->setHostname("github.com");
        $settingsEntity->setProjectRegex("(?<=github.com\/)([a-zA-Z-]+)");
        $settingsEntity->setIssueRegex("\d+$");
        return $settingsEntity;
    }

    public function getPredefinedNextCloudDeck()
    {
        $settingsEntity = new SettingEntity();
        $settingsEntity->setHostname("some.next.cloud");
        $settingsEntity->setProjectRegex("[0-9]+");
        $settingsEntity->setIssueRegex("");
        return $settingsEntity;
    }

    /**
     * @param $hostname
     * @return string
     */
    private function makeFilename($hostname): string
    {
        return $filename = $this->storage . '/' . $hostname . ".json";
    }
}
