<?php
namespace yuslf\ComposerInstallerPlugin;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Repository\InstalledRepositoryInterface;

class Installer extends LibraryInstaller
{
    /*public function getPackageBasePath(PackageInterface $package)
    {
        return 'case/' . $package->getPrettyName();
    }*/

    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::install($repo, $package);
        $this->filesystem->copy('jaeger_php/' . $package->getPrettyName(), './');
        $this->filesystem->remove('jaeger_php');
    }

    public function getInstallPath(PackageInterface $package)
    {
        return 'jaeger_php/' . $package->getPrettyName();
    }

    public function supports($packageType)
    {
        return 'composer-use-case' === $packageType;
    }
}