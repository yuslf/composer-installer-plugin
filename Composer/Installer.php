<?php
namespace yuslf\Composer;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;

class Installer extends LibraryInstaller
{
    public function getPackageBasePath(PackageInterface $package)
    {
        return 'case/' . $package->getPrettyName();
    }

    public function supports($packageType)
    {
        return 'composer-use-case' === $packageType;
    }
}