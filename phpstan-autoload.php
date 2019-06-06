<?php

require_once 'vendor/autoload.php';
autoloadPhpunit();

function autoloadPhpunit()
{
    $phpunitDir = getEnvVar('SYMFONY_PHPUNIT_DIR', 'vendor/bin/.phpunit');
    $autoloadFilePattern = "{$phpunitDir}/phpunit-*/vendor/autoload.php";

    $suitablePhpunitAutoloadFiles = glob($autoloadFilePattern);
    if (!isset($suitablePhpunitAutoloadFiles[0]) || !file_exists($suitablePhpunitAutoloadFiles[0])) {
        echo addRedBackground(
            'Для корректной работы phpstan будут установлены зависимости phpunit'
        ) . PHP_EOL;

        passthru('bin/phpunit --version');

        $suitablePhpunitAutoloadFiles = glob($autoloadFilePattern);
        if (!isset($suitablePhpunitAutoloadFiles[0]) || !file_exists($suitablePhpunitAutoloadFiles[0])) {
            echo addRedBackground(
                "По пути {$autoloadFilePattern} не найден файл автозагрузки phpunit, не удалось установить зависимости phpunit. Попробуйте вручную запустить bin/phpstan"
            ) . PHP_EOL;
            exit();
        }
    }

    require_once $suitablePhpunitAutoloadFiles[0];
}

// взято из реализации vendor/symfony/phpunit-bridge/bin/simple-phpunit
function getEnvVar($name, $default = false)
{
    if (false !== $value = getenv($name)) {
        return $value;
    }

    static $phpunitConfig = null;
    if ($phpunitConfig === null) {
        $phpunitConfigFilename = null;
        if (file_exists('phpunit.xml')) {
            $phpunitConfigFilename = 'phpunit.xml';
        } elseif (file_exists('phpunit.xml.dist')) {
            $phpunitConfigFilename = 'phpunit.xml.dist';
        }
        if ($phpunitConfigFilename) {
            $phpunitConfig = new DOMDocument();
            $phpunitConfig->load($phpunitConfigFilename);
        } else {
            $phpunitConfig = false;
        }
    }
    if ($phpunitConfig !== false) {
        $var = new DOMXPath($phpunitConfig);
        foreach ($var->query('//php/env[@name="' . $name . '"]') as $var) {
            return $var->getAttribute('value');
        }
    }

    return $default;
}

function addRedBackground($text)
{
    return "\e[41m{$text}\e[0m";
}
