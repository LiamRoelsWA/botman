<?php


namespace BotMan\BotMan\Middleware;


use BotMan\BotMan\BotMan;
use Google\ApiCore\ValidationException;
use Google\ApiCore\ApiException;
use BotMan\BotMan\Interfaces\MiddlewareInterface;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use Google\Cloud\Dialogflow\V2\QueryInput;
use Google\Cloud\Dialogflow\V2\SessionsClient;
use Google\Cloud\Dialogflow\V2\TextInput;


/**
 * Class DialogflowV2Middleware
 *
 * @package Middleware
 *
 * @author Liam Roels <liam@webatvantage.be>
 */
class DialogflowV2Middleware implements MiddlewareInterface
{
    /** @var string */
    protected $token;


    /** @var string */
    protected $lang = 'en';


    /** @var */
    protected $response;


    /** @var bool */
    protected $listenForAction = false;


    /** @var string */
    protected $projectID;


    /**
     * DialogflowV2Middleware constructor.
     * @param string $token path to google cloud json authentication token
     * @param string $projectID projectID of dialogflow agent
     * @param string $lang
     */
    public function __construct($token, $projectID, $lang = 'en')
    {
        $this->token = $token;
        $this->projectID = $projectID;
        $this->lang = $lang;
    }


    /**
     * @param string $token path to google cloud json authentication token
     * @param string $projectID projectID of dialogflow agent
     * @param string $lang
     *
     * @return DialogflowV2Middleware
     */
    public static function create($token, $projectID, $lang = 'en')
    {
        return new static($token, $projectID, $lang);
    }


    /**
     * @param bool $listen
     *
     * @return $this
     */
    public function listenForAction($listen = true)
    {
        $this->listenForAction = $listen;

        return $this;
    }


    /**
     * @param IncomingMessage $message
     *
     * @return mixed
     *
     * @throws ApiException
     * @throws ValidationException
     */
    protected function getResponse(IncomingMessage $message)
    {
        $test = array('credentials' => $this->token);
        $sessionsClient = new SessionsClient($test);
        $session = $sessionsClient->sessionName($this->projectID, md5($message->getConversationIdentifier()));

        $textInput = new TextInput();
        $textInput->setText($message->getText());
        $textInput->setLanguageCode($this->lang);

        // create query input
        $queryInput = new QueryInput();
        $queryInput->setText($textInput);

        // get response and relevant info
        $response = $sessionsClient->detectIntent($session, $queryInput);

        $this->response = json_decode($response->serializeToJsonString());

        return $this->response;
    }


    /**
     * @param IncomingMessage $message
     * @param callable $next
     * @param BotMan $bot
     *
     * @return mixed
     */
    public function captured(IncomingMessage $message, $next, BotMan $bot)
    {
        return $next($message);
    }


    /**
     * @param IncomingMessage $message
     * @param callable $next
     * @param BotMan $bot
     *
     * @return mixed
     *
     * @throws ApiException
     * @throws ValidationException
     */
    public function received(IncomingMessage $message, $next, BotMan $bot)
    {
        $response = $this->getResponse($message);

        $reply = $response->queryResult->fulfillmentText ?? '';
        $action = $response->queryResult->action ?? '';
        $actionIncomplete = isset($response->queryResult->allRequiredParamsCollected) ? (bool) $response->queryResult->allRequiredParamsCollected : false;
        $intent = $response->queryResult->intent->displayName ?? '';
        $parameters = isset($response->queryResult->parameters) ? (array) $response->queryResult->parameters : [];
        $message->addExtras('apiReply', $reply);
        $message->addExtras('apiAction', $action);
        $message->addExtras('apiActionIncomplete', $actionIncomplete);
        $message->addExtras('apiIntent', $intent);
        $message->addExtras('apiParameters', $parameters);

        return $next($message);
    }


    /**
     * @param IncomingMessage $message
     * @param string $pattern
     * @param bool $regexMatched
     *
     * @return bool
     */
    public function matching(IncomingMessage $message, $pattern, $regexMatched)
    {
        if ($this->listenForAction) {
            $pattern = '/^'.$pattern.'$/i';

            return (bool) preg_match($pattern, $message->getExtras()['apiAction']);
        }

        return true;
    }


    /**
     * @param IncomingMessage $message
     * @param callable $next
     * @param BotMan $bot
     *
     * @return mixed
     */
    public function heard(IncomingMessage $message, $next, BotMan $bot)
    {
        return $next($message);
    }


    /**
     * @param mixed $payload
     * @param callable $next
     * @param BotMan $bot
     *
     * @return mixed
     */
    public function sending($payload, $next, BotMan $bot)
    {
        return $next($payload);
    }
}