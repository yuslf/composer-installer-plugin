<?php
namespace yuslf\ComposerInstallerPlugin;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;

class Installer extends LibraryInstaller
{
    /*public function getPackageBasePath(PackageInterface $package)
    {
        return 'case/' . $package->getPrettyName();
    }*/

    public function getInstallPath(PackageInterface $package)
    {
        return './';
    }

    public function supports($packageType)
    {
        return 'composer-use-case' === $packageType;
    }
}