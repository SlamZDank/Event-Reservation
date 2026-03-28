<?php

namespace App\Repository;

use App\Entity\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Setting>
 */
class SettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    public function getValue(string $key): ?string
    {
        $setting = $this->findOneBy(['settingKey' => $key]);
        return $setting?->getSettingValue();
    }

    public function setValue(string $key, ?string $value): void
    {
        $setting = $this->findOneBy(['settingKey' => $key]);
        if (!$setting) {
            $setting = new Setting();
            $setting->setSettingKey($key);
            $this->getEntityManager()->persist($setting);
        }
        $setting->setSettingValue($value);
        $this->getEntityManager()->flush();
    }
}
