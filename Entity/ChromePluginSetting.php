<?php

namespace KimaiPlugin\ChromePluginBundle\Entity;

class ChromePluginSetting
{
    private $durationOnly = true;
    private $showTags = false;
    private $showFixedRate = false;
    private $showHourlyRate = false;

    /**
     * @return bool
     */
    public function isDurationOnly(): bool
    {
        return $this->durationOnly;
    }

    /**
     * @param bool $durationOnly
     * @return ChromePluginSetting
     */
    public function setDurationOnly(bool $durationOnly): ChromePluginSetting
    {
        $this->durationOnly = $durationOnly;
        return $this;
    }

    /**
     * @return bool
     */
    public function isShowTags(): bool
    {
        return $this->showTags;
    }

    /**
     * @param bool $showTags
     * @return ChromePluginSetting
     */
    public function setShowTags(bool $showTags): ChromePluginSetting
    {
        $this->showTags = $showTags;
        return $this;
    }

    /**
     * @return bool
     */
    public function isShowFixedRate(): bool
    {
        return $this->showFixedRate;
    }

    /**
     * @param bool $showFixedRate
     * @return ChromePluginSetting
     */
    public function setShowFixedRate(bool $showFixedRate): ChromePluginSetting
    {
        $this->showFixedRate = $showFixedRate;
        return $this;
    }

    /**
     * @return bool
     */
    public function isShowHourlyRate(): bool
    {
        return $this->showHourlyRate;
    }

    /**
     * @param bool $showHourlyRate
     * @return ChromePluginSetting
     */
    public function setShowHourlyRate(bool $showHourlyRate): ChromePluginSetting
    {
        $this->showHourlyRate = $showHourlyRate;
        return $this;
    }


}
