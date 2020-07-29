<?php
declare(strict_types=1);

namespace KimaiPlugin\ChromePluginBundle\Repository;

/**
 * Class ChromeSettingRepository
 */
final class ChromeSettingRepository
{
    /**
     * @var string
     */
    private string $storage;

    /**
     * ChromeSettingRepository constructor.
     * @param string $dataDirectory
     */
    public function __construct(string $dataDirectory)
    {
        $this->storage = $dataDirectory . '/chromeSetting.json';
    }

    /**
     * @param string $hostname
     * @return array
     */
    public function findByhostname(string $hostname): array
    {
        $all_settings = $this->findAll();
        if (array_key_exists($hostname, $all_settings)) {
            return $all_settings[$hostname];
        }
        return [];
    }

    /**
     * @return array
     */
    public function findAll(): array
    {
        if (!file_exists($this->storage)) {
            return [];
        }

        $raw_data = file_get_contents($this->storage);
        return json_decode($raw_data, true);
    }

    /**
     * @param string $hostname
     * @param array $data
     */
    public function save(string $hostname, array $data)
    {
        $all_settings = $this->findAll();
        $all_settings[$hostname] = $data;
        $this->saveAll($all_settings);
    }

    /**
     * @param array $settings
     */
    public function saveAll(array $settings)
    {
        file_put_contents($this->storage, json_encode($settings));
    }

    /**
     * @param $hostname
     */
    public function remove($hostname)
    {
        $all_settings = $this->findAll();
        if (array_key_exists($hostname, $all_settings)) {
            unset($all_settings[$hostname]);
            $this->saveAll($all_settings);
        }
    }
}
