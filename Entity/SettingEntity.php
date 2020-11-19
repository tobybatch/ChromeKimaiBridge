<?php


namespace KimaiPlugin\ChromePluginBundle\Entity;


/**
 * Class SettingEntity
 * @package KimaiPlugin\ChromePluginBundle\Entity
 */
class SettingEntity
{
    private string $hostname;
    private string $projectRegex;
    private string $issueRegex;

    /**
     * @return string
     */
    public function getHostname(): string
    {
        return $this->hostname ?? "";
    }

    /**
     * @param string $hostname
     */
    public function setHostname(string $hostname): void
    {
        $this->hostname = $hostname;
    }

    /**
     * @return string
     */
    public function getProjectRegex(): string
    {
        return $this->projectRegex ?? "";
    }

    /**
     * @param string $projectRegex
     */
    public function setProjectRegex(string $projectRegex): void
    {
        $this->projectRegex = $projectRegex;
    }

    /**
     * @return string
     */
    public function getIssueRegex(): string
    {
        return $this->issueRegex ?? "";
    }

    /**
     * @param string $issueRegex
     */
    public function setIssueRegex(string $issueRegex): void
    {
        $this->issueRegex = $issueRegex;
    }

    /**
     * @return false|string
     */
    public function toJson() {
        $json_data = [
            'hostname' => $this->getHostname(),
            'projectRegex' => $this->getProjectRegex(),
            'issueRegex' => $this->getIssueRegex(),
        ];
        return json_encode($json_data);
    }

    public static function fromJson($raw_json) {
        $decoded = json_decode($raw_json, true);
        $setting = new SettingEntity();
        $setting->setHostname($decoded['hostname']);
        $setting->setProjectRegex($decoded['projectRegex']);
        $setting->setIssueRegex($decoded['issueRegex']);
        return $setting;
    }

    public function __toString()
    {
        return $this->toJson();
    }

}