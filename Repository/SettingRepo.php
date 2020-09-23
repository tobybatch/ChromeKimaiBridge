<?php
declare(strict_types=1);

namespace KimaiPlugin\ChromePluginBundle\Repository;

use KimaiPlugin\ChromePluginBundle\Entity\SettingEntity;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

/**
 * Class ChromeSettingRepository
 *
 * @method SettingEntity findOneBy(int $id) Fetch one entity by id
 */
class SettingRepo
{
    private LoggerInterface $logger;
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
     * @param LoggerInterface $logger
     * @param string $dataDirectory
     */
    public function __construct(ContainerInterface $container, LoggerInterface $logger, string $dataDirectory)
    {
        $this->logger = $logger;
        $this->filesystem = new Filesystem();

        $this->storage = $dataDirectory . '/chromeSetting/' . $container->getParameter('kernel.environment');
        if (!$this->filesystem->exists($this->storage)) {
            // first run, add github
            $logger->info("Creating storage dir, adding github");

            $this->filesystem->mkdir($this->storage, 0775);

            $chrome_setting = new SettingEntity();
            $chrome_setting->setHostname("github.com");
            $chrome_setting->setRegex1("(?<=github.com\/)([a-zA-Z-]+)");
            $chrome_setting->setRegex2("\d+$");
            $this->save($chrome_setting);
        }
    }

    /**
     * @param SettingEntity $setting
     */
    public function save(SettingEntity $setting)
    {
        $filename = $this->makeFilename($setting->getHostname());
        file_put_contents($filename, $setting->toJson());
        $this->logger->debug(__METHOD__, ['filename' => $filename, 'entity' => $setting]);
    }

    /**
     * @param $hostname
     * @return string
     */
    private function makeFilename($hostname): string
    {
        return $filename = $this->storage . '/' . $hostname . ".json";
    }

    /**
     * @return array
     */
    public function findAll(): array
    {
        // Symfony does not have a ls dir function
        $file_list = glob($this->storage . '/*.json');
        $this->logger->debug(__METHOD__, ['count' => count($file_list)]);
        $settings = [];
        foreach ($file_list as $filename) {
            $settings[] = $this->findByHostname(basename($filename, ".json"));
        }
        return $settings;
    }

    /**
     * @param string $hostname
     * @return SettingEntity|bool
     */
    public function findByHostname(string $hostname): SettingEntity
    {
        $filename = $this->makeFilename($hostname);
        if ($this->filesystem->exists($filename)) {
            $setting = SettingEntity::fromJson(file_get_contents($filename));
            $this->logger->debug(__METHOD__, [
                'name' => $hostname,
                'path' => $filename,
                'entity' => $setting
            ]);
            return $setting;
        }
        throw new FileNotFoundException($hostname);
    }

    /**
     * @param SettingEntity[] $settings
     */
    public function saveAll(array $settings)
    {
        $this->logger->debug(__METHOD__, ['count' => count($settings)]);
        foreach ($settings as $setting) {
            $this->save($setting);
        }
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
        $this->logger->debug(__METHOD__, ['count' => count($file_list)]);
        foreach ($file_list as $filename) {
            $this->logger->info("Remove entity", ['filename' => $filename]);
            $this->filesystem->remove($filename);
        }
    }

    public function removeByHost($hostname)
    {
        $filename = $this->makeFilename($hostname);
        if ($this->filesystem->exists($filename)) {
            $this->logger->info("Remove entity", ['filename' => $filename]);
            $this->filesystem->remove($filename);
            return true;
        } else {
            $this->logger->warning("Entity does not exist", ['filename' => $filename]);
            return false;
        }
    }

    protected function getPredefinedGitHub() {
        $settingsEntity = new SettingEntity();
        $settingsEntity->setHostname("github.com");
        $settingsEntity->setRegex1("(?<=github.com\/)([a-zA-Z-]+)");
        $settingsEntity->setRegex2("\d+$");
        return $settingsEntity;
    }

    protected function getPredefinedNextCloudDeck() {
        $settingsEntity = new SettingEntity();
        $settingsEntity->setHostname("some.next.cloud");
        $settingsEntity->setRegex1("[0-9]+");
        $settingsEntity->setRegex2("");
        return $settingsEntity;
    }

}
