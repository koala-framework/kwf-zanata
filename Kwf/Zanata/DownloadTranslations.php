<?php
namespace Kwf\Zanata;
use Psr\Log\LoggerInterface;
use Kwf\Zanata\Config\ConfigInterface;

class DownloadTranslations
{
    static $TEMP_TRL_FOLDER = 'koala-framework-zanata-trl';
    static $TEMP_LAST_UPDATE_FILE = 'last_update.txt';
    static $COMPOSER_EXTRA_KEY = 'kwf-zanata';

    protected $_logger;
    protected $_config;
    protected $_updateDownloadedTrlFiles = false;

    public function __construct(LoggerInterface $logger, ConfigInterface $config)
    {
        $this->_logger = $logger;
        $this->_config = $config;
    }

    public function setForceDownloadTrlFiles($download)
    {
        $this->_updateDownloadedTrlFiles = $download;
    }

    public static function getComposerJsonFiles()
    {
        $files = glob('vendor/*/*/composer.json');
        array_unshift($files, 'composer.json');
        return $files;
    }

    private function _getTempFolder($project = null, $version = null)
    {
        $path = sys_get_temp_dir().'/'.DownloadTranslations::$TEMP_TRL_FOLDER;
        if ($version && $project) {
            $path .= "/$project/$version";
        }
        return $path;
    }

    private function _getLastUpdateFile($project, $version)
    {
        return $this->_getTempFolder($project, $version).'/'.DownloadTranslations::$TEMP_LAST_UPDATE_FILE;
    }

    private function _checkDownloadTrlFiles($project, $version)
    {
        if ($this->_updateDownloadedTrlFiles) return true;
        $downloadFiles = true;
        if (file_exists($this->_getLastUpdateFile($project, $version))) {
            $lastDownloadTimestamp = strtotime(substr(file_get_contents($this->_getLastUpdateFile($project, $version)), 0, strlen('HHHH-MM-DD')));
            $downloadFiles = strtotime('today') > $lastDownloadTimestamp;
        }
        return $downloadFiles;
    }

    public function downloadTrlFiles()
    {
        $this->_logger->info('Iterating over packages and downloading trl-resources');
        $composerJsonFilePaths = DownloadTranslations::getComposerJsonFiles();
        foreach ($composerJsonFilePaths as $composerJsonFilePath) {
            $composerJsonFile = file_get_contents($composerJsonFilePath);
            $composerConfig = json_decode($composerJsonFile);

            if (!isset($composerConfig->extra->{self::$COMPOSER_EXTRA_KEY})) continue;

            $extraData = $composerConfig->extra->{self::$COMPOSER_EXTRA_KEY};
            $projectName = strtolower($extraData->project);
            $version = strtolower($extraData->version);
            $trlTempDir = $this->_getTempFolder($projectName, $version);
            if ($this->_checkDownloadTrlFiles($projectName, $version)) {
                if (!file_exists($trlTempDir)) {
                    mkdir($trlTempDir, 0777, true);//write and read for everyone
                }
                $this->_logger->warning("Checking/Downloading resources of {$extraData->project}/{$extraData->version}");
                $this->_downloadAndSaveProjectTranslationFiles($extraData);
                file_put_contents($this->_getLastUpdateFile($projectName, $version), date('Y-m-d H:i:s'));
            }
            if (!file_exists(dirname($composerJsonFilePath).'/trl/')) {
                mkdir(dirname($composerJsonFilePath).'/trl/', 0777, true);//write and read for everyone
            }
            foreach (scandir($trlTempDir) as $file) {
                if (substr($file, 0, 1) === '.') continue;
                copy($trlTempDir.'/'.$file, dirname($composerJsonFilePath).'/trl/'.basename($file));
            }
        }
    }

    private function _downloadAndSaveProjectTranslationFiles($extraData)
    {
        $trlTempDir = $this->_getTempFolder($extraData->project, $extraData->version);
        $params = array( 'auth_token' => $this->_config->getApiToken() );
        $localesUrl = $extraData->restApiUrl."/project/{$extraData->project}/version/{$extraData->version}/locales";
        $content = $this->_downloadFile($localesUrl);
        if ($content === false) {
            throw new ZanataException('Service unavailable');
        }
        $locales = json_decode($content);
        if ($locales == null) {
            throw new ZanataException('No json returned');
        }
        foreach ($locales as $locale) {
            $poFilePath = $trlTempDir.'/'.$locale->localeId.'.po';
            $this->_logger->notice("Downloading {$locale->localeId}");
            $url = $extraData->restApiUrl."/file/translation/{$extraData->project}/{$extraData->version}/{$locale->localeId}/po?docId={$extraData->docId}";
            $this->_logger->info('Calling Url: '.$url);
            $file = $this->_downloadFile($url);
            if ($file === false) {
                throw new ZanataException('Download file from Zanata failed: '.$url);
            }
            if (strpos($file, '"Content-Type: text/plain; charset=UTF-8"') === false) {
                $poHeader = "msgid \"\"\n"
                           ."msgstr \"\"\n"
                           ."\"Content-Type: text/plain; charset=UTF-8\"\n\n";
                $file = $poHeader.$file;
            }
            file_put_contents($poFilePath, $file);
        }
        file_put_contents($this->_getLastUpdateFile($extraData->project, $extraData->version), date('Y-m-d H:i:s'));
    }

    private function _downloadFile($url)
    {
        $this->_logger->debug("fetching $url");
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'X-Auth-User: '.$this->_config->getUser(),
            'X-Auth-Token: '.$this->_config->getApiToken()
        ));

        $count = 0;
        $file = false;
        while ($file === false && $count < 5) {
            if ($count != 0) {
                sleep(5);
                $this->_logger->warning("Try again downloading file... {$url}");
            }
            $file = curl_exec($ch);
            $count++;
        }
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
            throw new ZanataException('Request to '.$url.' failed with '.curl_getinfo($ch, CURLINFO_HTTP_CODE).': '.$file);
        }
        return $file;
    }
}
