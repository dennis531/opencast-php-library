<?php 
namespace OpencastApi;

use OpencastApi\Rest\OcRestClient;
use OpencastApi\Rest\OcIngest;

class OpenCast
{
    /** @var OpencastApi\Rest\OcRestClient the rest client */
    private $restClient;

    /** @var OpencastApi\Rest\OcRestClient the engage node rest client */
    private $engageRestClient;

    /* 
        $config = [
            'url' => 'https://develop.opencast.org/',       // The API url of the opencast instance (required)
            'username' => 'admin',                          // The API username. (required)
            'password' => 'opencast',                       // The API password. (required)
            'timeout' => 0,                                 // The API timeout. In seconds (default 0 to wait indefinitely). (optional)
            'connect_timeout' => 0                          // The API connection timeout. In seconds (default 0 to wait indefinitely) (optional)
            'version' => null                               // The API Version. (Default null). (optional)
        ]

        $engageConfig = [
            'url' => 'https://develop.opencast.org/',       // The API url of the opencast instance (required)
            'username' => 'admin',                          // The API username. (required)
            'password' => 'opencast',                       // The API password. (required)
            'timeout' => 0,                                 // The API timeout. In seconds (default 0 to wait indefinitely). (optional)
            'connect_timeout' => 0                          // The API connection timeout. In seconds (default 0 to wait indefinitely) (optional)
            'version' => null                               // The API Version. (Default null). (optional)
        ]
    */
    public function __construct($config, $engageConfig = [])
    {
        $this->restClient = new OcRestClient($config);
        $this->setEngageRestClient($config, $engageConfig);
        $this->setEndpointProperties($config);
    }

    private function setEndpointProperties($config)
    {
        foreach(glob(__DIR__   . '/Rest/*.php') as $classPath) {
            
            $className = basename($classPath, '.php');
            $fullClassName = "\\OpencastApi\\Rest\\{$className}";
            $propertyName = lcfirst(str_replace('Oc', '', $className));
            $client = $this->restClient;

            if (in_array($className, $this->excludeFilters()) || property_exists($this, $propertyName)) {
                continue;
            }

            if (in_array($className, $this->engageFilters())) {
                $client = $this->engageRestClient;
            }

            $versionFilters = $this->versionFilters();
            if (array_key_exists($className, $versionFilters) && !$client->hasVersion($versionFilters[$className])) {
                continue;
            }

            $this->{$propertyName} = new $fullClassName($client);
        }

        // NOTE: services must be instantiated before calling setIngest method!
        $this->setIngestProperty($config);
    }

    private function excludeFilters()
    {
        return [
            'OcRest',
            'OcRestClient',
            'OcIngest'
        ];
    }

    private function engageFilters()
    {
        return [
            'OcSearch'
        ];
    }

    private function versionFilters()
    {
        return [
            'OcWorkflowsApi' => '1.1.0',
            'OcAgentsApi' => '1.1.0',
            'OcStatisticsApi' => '1.3.0',
        ];
    }

    private function setEngageRestClient($config, $engageConfig)
    {
        if (!isset($engageConfig['url'])) {
            $engageConfig['url'] = $config['url'];
        }
        if (!isset($engageConfig['username'])) {
            $engageConfig['username'] = $config['username'];
        }
        if (!isset($engageConfig['password'])) {
            $engageConfig['password'] = $config['password'];
        }
        if (!isset($engageConfig['timeout']) && isset($config['timeout'])) {
            $engageConfig['timeout'] = $config['timeout'];
        }
        if (!isset($engageConfig['version']) && isset($config['version'])) {
            $engageConfig['version'] = $config['version'];
        }
        $this->engageRestClient = new OcRestClient($engageConfig);
    }

    private function setIngestProperty($config)
    {
        if (!property_exists($this, 'services')) {
            return;
        }
        $servicesJson = $this->services->getServiceJSON('org.opencastproject.ingest');
        if (!empty($servicesJson['body']) && property_exists($servicesJson['body'], 'services')) {
            $service = $servicesJson['body']->services->service;
            if (is_array($service)) {
                // Choose random ingest service.
                $ingestService = $service[array_rand($service)];
            } else {
                // There is only one.
                $ingestService = $service;
            }

            $ingestClient = $this->restClient;
            if ($config['url'] != $ingestService->host) {
                $config['url'] = $ingestService->host;
                $ingestClient = new OcRestClient($config);
            }

            $this->ingest = new OcIngest($ingestClient);
        }
    }

    public function __debugInfo()
    {
        return [];
    }
}
?>