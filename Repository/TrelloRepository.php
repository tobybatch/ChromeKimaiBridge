<?php
namespace KimaiPlugin\TrelloBundle\Repository;

use KimaiPlugin\TrelloBundle\Entity\TrelloSetting;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class TrelloRepository
{
    /**
     * @var string
     */
    protected $settingsFile;

    /**
     * @param string $dataDirectory
     */
    public function __construct(string $dataDirectory)
    {
        $this->settingsFile = $dataDirectory . '/chrome-ext';
    }

    /**
     * @param TrelloSetting $entity
     * @return bool
     * @throws \Exception
     */
    public function saveConfig(TrelloSetting $settings)
    {
        if (file_exists($this->settingsFile) && !is_writable($this->settingsFile)) {
            throw new \Exception('Settings file is not writable: ' . $this->settingsFile);
        }
        if (false === file_put_contents($this->settingsFile, self::toJson($settings))) {
            throw new \Exception('Failed saving custom css rules to file: ' . $this->settingsFile);
        }
        return true;
    }
    /**
     * @return TrelloSetting
     */
    public function getConfig()
    {
        $entity = new TrelloSetting();
        if (file_exists($this->settingsFile) && is_readable($this->settingsFile)) {
            $entity = self::fromJson(file_get_contents($this->settingsFile));
        }
        return $entity;
    }

    /**
     * @param string $json
     * @return TrelloSetting
     */
    public static function fromJson(string $json) {
        $data = json_decode($json, true);
        $entity = new TrelloSetting();

        if (array_key_exists('durationOnly', $data)) {
            $entity->setDurationOnly($data['durationOnly']);
        }

        if (array_key_exists('showTags', $data)) {
            $entity->setShowTags($data['showTags']);
        }

        if (array_key_exists('showFixedRate', $data)) {
            $entity->setShowFixedRate($data['showFixedRate']);
        }

        if (array_key_exists('showHourlyRate', $data)) {
            $entity->setShowHourlyRate($data['showHourlyRate']);
        }

        return $entity;
    }

    /**
     * @param TrelloSetting $settings
     * @return false|string
     */
    public static function toJson(TrelloSetting $settings) {
        return json_encode(self::toArray($settings), JSON_PRETTY_PRINT);
    }

    /**
     * @param TrelloSetting $settings
     * @return false|string
     */
    public static function toArray(TrelloSetting $settings) {
        $data = [
            'durationOnly' => $settings->isDurationOnly(),
            'showTags' => $settings->isShowTags(),
            'showFixedRate' => $settings->isShowFixedRate(),
            'showHourlyRate' => $settings->isShowHourlyRate(),
        ];
        return $data;
    }

}