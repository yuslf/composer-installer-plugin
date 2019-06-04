<?php
namespace yuslf\ComposerInstallerPlugin;

use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Repository\InstalledRepositoryInterface;

class Installer extends LibraryInstaller
{
    protected static function getTargetDir($source, $target)
    {
        if (! is_dir($source)) {
            return false;
        }

        $dh = opendir($source);
        if (! $dh) {
            return false;
        }

        $res = [];

        while (($file = readdir($dh)) !== false)
        {
            if ('.' === $file or '..' == $file) {
                continue;
            }
            if ('dir' === filetype($source . '/' . $file)) {
                $tmp = static::getTargetDir($source . '/' . $file, $target . '/' . $file);
                if ($tmp and is_array($tmp)) {
                    $res = array_merge($res, $tmp);
                }
                continue;
            }
            $res[] = [$source . '/' . $file, $target . '/' . $file];
        }
        return $res;
    }

    protected static function copyExtraFile($files, IOInterface $io)
    {
        foreach ($files as $f)
        {
            $ask = '    >>目标文件[' . $f[1] . ']已经存在,是否覆盖[y|n]?';
            if (file_exists($f[1]) and ! $io->askConfirmation($ask)) {
                $f[1] .= '.jaeger';
                $io->write('    >>扩展文件更名为[' . $f[1] . '],请安装成功后自行合并!');
            }

            $targetPath = dirname($f[1]);
            if (! file_exists($targetPath)) {
                @ mkdir($targetPath, '755', true);
            }
            $io->write('    >>[' . $f[1] . '], ' . ((@ copy($f[0], $f[1])) ? 'Done.' : 'Failed.'));
        }
    }

    protected static function readConfigFile($path)
    {
        if (! file_exists($path)) {
            return false;
        }

        $config = file_get_contents($path);
        if (empty($config)) {
            return false;
        }

        return $config;
    }

    protected static function writeConfigFile($path, $content)
    {
        if (! file_exists($path)) {
            return false;
        }

        $res = file_put_contents($path, $content);
        if (! $res) {
            return false;
        }

        return $res;
    }

    protected static function getAppendConfigPattern($type, $field, $value)
    {
        $notes = "/*\n        * Jaeger-PHP\n        */\n";
        $pattern = '';
        $replacement = '';

        switch($type)
        {
            case 'array':
                $pattern = "/[\"|']{$field}[\"|']\s*=>\s*\[(.*?),{0,1}\s*\]/is";
                $replacement = "'{$field}' => [\n\${1},\n\n        {$notes}";
                break;

            case 'property' :
                $pattern = "/\${$field}\s*=\s*\[(.*?),{0,1}\s*\]/is";
                $replacement = "\${$field} = [\n\${1},\n\n        {$notes}";
                break;
        }

        if (! is_array($value)) {
            $value = [$value];
        }
        foreach ($value as $v)
        {
            $replacement .= "        {$v},\n";
        }
        $replacement .= "\n    ]";

        return ['pattern' => $pattern, 'replacement' => $replacement];
    }

    protected static function appendArrayConfig($config, $field, $value)
    {
        $p = static::getAppendConfigPattern('array', $field, $value);

        return preg_replace($p['pattern'], $p['replacement'], $config);
    }

    protected static function appendPropertyConfig($config, $field, $value)
    {
        $p = static::getAppendConfigPattern('property', $field, $value);

        return preg_replace($p['pattern'], $p['replacement'], $config);
    }

    protected static function getReplaceConfigPattern($type, $field, $old, $new)
    {
        $pattern = '';
        $replacement = '';

        switch($type)
        {
            case 'array':
                $pattern = "/[\"|']{$field}[\"|']\s*=>\s*\[(.*?)(" . addslashes($old) . ")(.*?)]/is";
                $replacement = "'{$field}' => [\n\${1}\\\\\\\\Jaeger-PHP:{$old},\n        {$new}\${3}]";
                break;

            case 'property' :
                $pattern = "/\${$field}\s*=\s*\[(.*?)(" . addslashes($old) . ")(.*?)]/is";
                $replacement = "\${$field} = [\n\${1}\\\\\\\\Jaeger-PHP:{$old},\n        {$new}\${3}]";
                break;
        }

        return ['pattern' => $pattern, 'replacement' => $replacement];
    }

    protected static function replaceArrayConfig($config, $field, $old, $new)
    {
        $p = static::getReplaceConfigPattern('array', $field, $old, $new);

        return preg_replace($p['pattern'], $p['replacement'], $config);
    }

    protected static function replacePropertyConfig($config, $field, $old, $new)
    {
        $p = static::getReplaceConfigPattern('property', $field, $old, $new);

        return preg_replace($p['pattern'], $p['replacement'], $config);
    }

    /*public function getPackageBasePath(PackageInterface $package)
    {
        return 'case/' . $package->getPrettyName();
    }*/

    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::install($repo, $package);

        $this->io->write('>>Jaeger-PHP:正在安装PHP框架扩展文件,以便使用Jaeger—PHP客户端.');

        $extraFiles = static::getTargetDir('jaeger_php/' . $package->getPrettyName(), './');
        static::copyExtraFile($extraFiles, $this->io);

        $this->filesystem->remove('jaeger_php');

        $this->io->write("\n>>Jaeger-PHP:尝试更新配置文件[config/app.php].");
        $config = static::readConfigFile('./config/app.php');
        if (! $config) {
            $this->io->error(">>Jaeger-PHP:配置文件[config/app.php]读取失败!");
        } else {
            $config = static::appendArrayConfig($config, 'providers', 'App\Providers\JaegerDbServiceProvider::class');
            $config = static::appendArrayConfig($config, 'aliases', "'HttpClient' => App\Facades\HttpClient::class");
            $config = static::replaceArrayConfig($config, 'providers',
                'Illuminate\Redis\RedisServiceProvider::class', 'App\Illuminate\Redis\RedisServiceProvider::class');
            if (! static::writeConfigFile('./config/app.php', $config)) {
                $this->io->error(">>Jaeger-PHP:配置文件[config/app.php]更新失败!");
            }
            $this->io->write(">>Jaeger-PHP:配置文件[config/app.php]更新成功!");
        }

        $this->io->write("\n>>Jaeger-PHP:尝试更新配置文件[app/Http/Kernel.php].");
        $config = static::readConfigFile('./app/Http/Kernel.php');
        if (! $config) {
            $this->io->error(">>Jaeger-PHP:配置文件[app/Http/Kernel.php]读取失败!");
        } else {
            $config = static::appendPropertyConfig($config, 'middleware', '\App\Http\Middleware\JaegerBefore::class');
            $config = static::appendPropertyConfig($config, 'middleware', '\App\Http\Middleware\JaegerAfter::class');
            if (! static::writeConfigFile('./app/Http/Kernel.php', $config)) {
                $this->io->error(">>Jaeger-PHP:配置文件[app/Http/Kernel.php]更新失败!");
            }
            $this->io->write(">>Jaeger-PHP:配置文件[app/Http/Kernel.php]更新成功!");
        }

        $this->io->write("\n>>Jaeger-PHP:尝试更新配置文件[app/Providers/EventServiceProvider.php].");
        $config = static::readConfigFile('./app/Providers/EventServiceProvider.php');
        if (! $config) {
            $this->io->error(">>Jaeger-PHP:配置文件[app/Providers/EventServiceProvider.php]读取失败!");
        } else {
            $value = "'App\Events\JaegerStartSpan' => [\n        'App\Listeners\JaegerStartSpanListener',\n    ]";
            $config = static::appendPropertyConfig($config, 'listen', $value);
            if (! static::writeConfigFile('./app/Providers/EventServiceProvider.php', $config)) {
                $this->io->error(">>Jaeger-PHP:配置文件[app/Providers/EventServiceProvider.php]更新失败!");
            }
            $this->io->write(">>Jaeger-PHP:配置文件[app/Providers/EventServiceProvider.php]更新成功!");
        }

        $this->io->write("\n>>Jaeger-PHP:尝试更新配置文件[config/jeager.php].");
        $serviceName = trim($this->io->ask('    >>请输入服务名称:', 'CustomJaegerServiceName'));
        $serviceVersion = trim($this->io->ask('    >>请输入版本号:', '0.0.1'));
        $collector = trim($this->io->ask('    >>请输入收集器地址:', '172.17.90.34:6831'));
        $content = <<<EOT
<?php
return [
    'service_name' => '{$serviceName}',
    'service_version' => '{$serviceVersion}',
    'collector' => '{$collector}',
];
EOT;
        if (! static::writeConfigFile('./config/jeager.php', $content)) {
            $this->io->error(">>Jaeger-PHP:配置文件[config/jeager.php]更新失败!");
        } else {
            $this->io->write(">>Jaeger-PHP:配置文件[config/jeager.php]更新成功!");
        }

        $this->io->write("\n>>Jaeger-PHP:尝试更新配置文件[app/Http/routes.php].");
        $config = static::readConfigFile('./app/Http/routes.php');
        if (! $config) {
            $this->io->error(">>Jaeger-PHP:配置文件[app/Http/routes.php]读取失败!");
        } else {
            $config .= "Route::get('/jaeger', 'JaegerController@test');";
            if (! static::writeConfigFile('./app/Http/routes.php', $config)) {
                $this->io->error(">>Jaeger-PHP:配置文件[app/Http/routes.php]更新失败!");
            }
            $this->io->write(">>Jaeger-PHP:配置文件[app/Http/routes.php]更新成功!");
        }

        $this->io->write(">>Jaeger-PHP:安装成功!");
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