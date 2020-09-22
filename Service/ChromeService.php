<?php

namespace KimaiPlugin\ChromePluginBundle\Service;

use http\Exception\RuntimeException;
use KimaiPlugin\ChromePluginBundle\Entity\SettingEntity;
use KimaiPlugin\ChromePluginBundle\Repository\SettingRepo;

/**
 * Class ChromeService
 * @package KimaiPlugin\ChromePluginBundle\Service
 */
class ChromeService
{

    /**
     * @var SettingRepo
     */
    private SettingRepo $settingRepo;

    public function __construct(SettingRepo $chromeSettingRepo)
    {
        $this->settingRepo = $chromeSettingRepo;
    }

    /**
     * @param string $uri_from_plugin
     * @return array
     */
    public function parseUriForIds(string $uri_from_plugin)
    {
        $setting = $this->fetchEntity($uri_from_plugin);
        if (!$setting) {
            throw new RuntimeException("Unknown URI " . $uri_from_plugin);
        }

        return $this->getBoardAndCardId($setting, $uri_from_plugin);
    }

    /**
     * @param SettingEntity $setting
     * @param string $uri_from_plugin
     * @return array
     */
    public function getBoardAndCardId(SettingEntity $setting, string $uri_from_plugin) {
        if (empty($setting->getRegex2())) {
            $matches = [];
            preg_match("/" . $setting->getRegex1() . "/", $uri_from_plugin, $matches);
            $board_id = $matches[0] ?? false;
            $card_id = $matches[1] ?? false;
        } else {
            $matches = [];
            preg_match("/" . $setting->getRegex1() . "/", $uri_from_plugin, $matches);
            $board_id = $matches[0] ?? false;
            $matches = [];
            preg_match("/" . $setting->getRegex2() . "/", $uri_from_plugin, $matches);
            $card_id = $matches[0] ?? false;
        }
        // Make the names URI safe
        $board_id = str_replace("/", "_", ((string)$board_id));
        $card_id = str_replace("/", "_", ((string)$card_id));

        return [
            'board_id' => $board_id,
            'card_id' => $card_id,
        ];
    }

    /**
     * @param string $uri_from_plugin
     * @return SettingEntity
     */
    public function fetchEntity(string $uri_from_plugin): SettingEntity {
        $hostname = parse_url($uri_from_plugin, PHP_URL_HOST);
        return $this->settingRepo->findByHostname($hostname);
    }
}