<?php


namespace KimaiPlugin\ChromePluginBundle\Entity;


/**
 * Class SettingEntity
 * @package KimaiPlugin\ChromePluginBundle\Entity
 */
class SettingEntity
{
    private string $hostname;
    private string $regex1;
    private string $regex2;

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
    public function getRegex1(): string
    {
        return $this->regex1 ?? "";
    }

    /**
     * @param string $regex1
     */
    public function setRegex1(string $regex1): void
    {
        $this->regex1 = $regex1;
    }

    /**
     * @return string
     */
    public function getRegex2(): string
    {
        return $this->regex2 ?? "";
    }

    /**
     * @param string $regex2
     */
    public function setRegex2(string $regex2): void
    {
        $this->regex2 = $regex2;
    }

    /**
     * @return false|string
     */
    public function toJson() {
        $json_data = [
            'hostname' => $this->getHostname(),
            'regex1' => $this->getRegex1(),
            'regex2' => $this->getRegex2(),
        ];
        return json_encode($json_data);
    }

    public static function fromJson($raw_json) {
        $decoded = json_decode($raw_json, true);
        $setting = new SettingEntity();
        $setting->setHostname($decoded['hostname']);
        $setting->setRegex1($decoded['regex1']);
        $setting->setRegex2($decoded['regex2']);
        return $setting;
    }

    public function __toString()
    {
        return $this->toJson();
    }

}