<?php 
declare(strict_types=1);

namespace Increazy\ApiExtenderV2\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

class WebClient implements WebClientInterface
{

    const RETRIES_LIMIT = 3;
    /**
     * @var \Laminas\Http\Client
     */
    private $httpClient;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;


    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $appID;

    /**
     * @var bool
     */
    private $isTest;

    /**
     * @var string
     */
    private $requestUrl;

    /**
     * @var string
     */
    private $requestHost;

    /**
     * @var string
     */
    private $event;
    /**
     * @var bool
     */
    private $shouldLog;

    public function __construct(
        \Laminas\Http\ClientFactory $clientFactory,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    )
    {
        $this->httpClient = $clientFactory->create();
        $this->scopeConfig = $scopeConfig;

        $this->logger = $logger;
        
        $this->appID = $this->scopeConfig->getValue('increazy_general/general/app', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $this->isTest = (bool) $this->scopeConfig->getValue('increazy_general/general/test', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $this->shouldLog = (bool) $this->scopeConfig->getValue('increazy_general/general/debug_webhook', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        
        
    }


    public function pushWebhook(array $content) 
    {
        if (!isset($content['app'])) {
            $content['app'] = $this->appID;
        }
        $bodyContent = json_encode($content);
        $this->log('submiting request to url: '. $this->requestUrl. ' Content: '.$bodyContent);
        $resultingStatusCode = $this->executeRequest($bodyContent);
        $this->log('status code == '.$resultingStatusCode);

        return $this;
    }

    /**
     * Warning this method is recursive
     * 
     * Makes request to webhook processor and retries if status code != 200
     * @param string $requestBody
     * @param int $tries quantity of trials 
     * 
     
     * 
     * @see RETRIES_LIMIT constant
     */
    protected function executeRequest(string $requestBody, int $tries = 0) : int
    {
        if ($tries >= self::RETRIES_LIMIT)
        {
            return 400;
        }
        try {
            $this->httpClient->setUri($this->requestUrl)
                             ->setRawBody($requestBody)
                             ->setMethod(\Laminas\Http\Request::METHOD_POST)
                             ->setHeaders([
                                'Host' => $this->requestHost,
                                'Content-Type' => 'application/json'
                             ]);
            
            $response = $this->httpClient->send();
            $statusCode = $response->getStatusCode();
            if ($statusCode != \Laminas\Http\Response::STATUS_CODE_200) {
                throw new \Exception("Request error");
            }
            return $statusCode;

            
        }catch (\Exception $err)
        {
            $this->log($err->getMessage()." will retry...", []);
            return $this->executeRequest($requestBody, $tries+1);
        }

        
        
    }

    public function initialize(string $entity, string $event)
    {
        $env = $this->isTest ? 'test' : '';
        $this->requestUrl = 'https://indexer' . $env . '.api.increazy.com/magento2/webhook/'.$entity;
        $this->requestHost = 'indexer' . $env . '.api.increazy.com';
        $this->event = $event;
        return $this;
    }


    /**
     * @param string $message
     * @param array $context
     */
    private function log($message, $context = [])
    {
        if ($this->shouldLog)
        {
            $this->logger->debug('[Increazy_ApiExtenderV2]  EVENT:'.$this->event .' '. $message, $context);
        }
        return $this;
    }
}