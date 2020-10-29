<?php
namespace Kwf\Zanata\Config;

class Config implements ConfigInterface
{
    protected $_apiToken;
    protected $_user;
    public function __construct()
    {
        if (isset($_ENV['KWF_ZANATA_USER'])) {
            $this->_apiToken = $_ENV['KWF_ZANATA_USER'];
        } else {
            $config = $this->_getZanataConfig();
            if (!isset($config->user)) {
                throw new \Exception("No API-Token found in $path! Cannot load resources without Api-Token!");
            }
            $this->_user = $config->user;
        }
        if (isset($_ENV['KWF_ZANATA_APITOKEN'])) {
            $this->_apiToken = $_ENV['KWF_ZANATA_APITOKEN'];
        } else {
            $config = $this->_getZanataConfig();
            if (!isset($config->apiToken)) {
                throw new \Exception("No API-Token found in $path! Cannot load resources without Api-Token!");
            }
            $this->_apiToken = $config->apiToken;
        }
    }

    private function _getZanataConfig()
    {
        $path = $this->_getHomeDir().'/koala-framework/kwf-zanata/config';
        if (!file_exists($path)) {
            throw new \Exception("No kwf-zanata config found! ($path)");
        }
        return json_decode(file_get_contents($path));
    }

    /**
    * Adopted from https://github.com/composer/composer/blob/9f9cff558e5f447165f4265f320b2b1178f18301/src/Composer/Factory.php
    */
    private function _getHomeDir()
    {
        if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
            if (!getenv('APPDATA')) {
                throw new \Exception("The APPDATA environment variable must be set for kwf-zanata to run correctly");
            }
            $home = strtr(getenv('APPDATA'), '\\', '/') . '/Config';
        } else {
            if (!getenv('HOME')) {
                throw new \Exception("The HOME environment variable must be set for kwf-zanata to run correctly");
            }
            $home = rtrim(getenv('HOME'), '/') . '/.config';
        }
        return $home;
    }

    public function getApiToken()
    {
        return $this->_apiToken;
    }

    public function getUser()
    {
        return $this->_user;
    }
}
