<?php
namespace Kwf\Zanata\Config;

interface ConfigInterface
{
    /**
     * Returns apiToken
     *
     * @return string API-Token
     */
    public function getApiToken();
    public function getUser();
}
