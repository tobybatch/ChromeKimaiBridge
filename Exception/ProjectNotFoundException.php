<?php

namespace KimaiPlugin\ChromePluginBundle\Exception;

use KimaiPlugin\ChromePluginBundle\Entity\SettingEntity;

class ProjectNotFoundException extends \RuntimeException
{
    protected SettingEntity $settingEntity;

    /**
     * ProjectNotFoundException constructor.
     * @param SettingEntity $settingEntity
     */
    public function __construct(SettingEntity $settingEntity)
    {
        $this->settingEntity = $settingEntity;
    }

    /**
     * @return SettingEntity
     */
    public function getSettingEntity(): SettingEntity
    {
        return $this->settingEntity;
    }

    /**
     * @param SettingEntity $settingEntity
     */
    public function setSettingEntity(SettingEntity $settingEntity): void
    {
        $this->settingEntity = $settingEntity;
    }
}