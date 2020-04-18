<?php

namespace KimaiPlugin\TrelloBundle\Entity;

class TrelloSetting
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
     * @return TrelloSetting
     */
    public function setDurationOnly(bool $durationOnly): TrelloSetting
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
     * @return TrelloSetting
     */
    public function setShowTags(bool $showTags): TrelloSetting
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
     * @return TrelloSetting
     */
    public function setShowFixedRate(bool $showFixedRate): TrelloSetting
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
     * @return TrelloSetting
     */
    public function setShowHourlyRate(bool $showHourlyRate): TrelloSetting
    {
        $this->showHourlyRate = $showHourlyRate;
        return $this;
    }


}
