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

    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $this->installCode($package);
        $this->binaryInstaller->installBinaries($package, $this->getInstallPath($package));
        if (!$repo->hasPackage($package)) {
            $repo->addPackage(clone $package);
        }
    }

    public function getInstallPath(PackageInterface $package)
    {
        return './';
    }

    public function supports($packageType)
    {
        return 'composer-use-case' === $packageType;
    }
}