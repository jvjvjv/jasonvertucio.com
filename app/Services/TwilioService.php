<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

use Twilio\Rest\Client;

class TwilioService
{
    private string $sid;
    private string $token;
    private Client $client;
    private string $fromPhone;

    public function __construct()
    {
        $this->sid = env("TWILIO_SID");
        $this->token = env("TWILIO_TOKEN");
        $this->fromPhone = env("TWILIO_FROM");

        $this->client = new Client($this->sid, $this->token);
    }

    /**
     * sendMessage
     * Sends a message to the specified recipient
     *
     * @param string $message The message to send (160-character max)
     * @param string $recipient The recipient's phone number
     * @param string $sender optional, send from a different phone number than the one specified in the .env file
     *
     * @return obj Message object
     */
    public function sendMessage(string $message, string $recipient, $sender = null)
    {
        if ($sender == null) {
            $sender = $this->fromPhone;
        }
        $start = microtime(true);
        $message = $this->client->messages->create($recipient, [
            "from" => $sender,
            "body" => $message
        ]);

        $end = microtime(true);
        Log::info("API: Message to " . $recipient . " took " . ($end - $start) . "ms");

        return $message;
    }

    /**
     * sendMessageWithCallback
     * Sends a message to the specified recipient
     *
     * @param string $message The message to send (160-character max)
     * @param string $recipient The recipient's phone number
     * @param string $sender optional, send from a different phone number than the one specified in the .env file
     *
     * @return obj Message object
     */
    public function sendMessageWithCallback($message, $recipient, $sender = null)
    {
        if ($sender == null) {
            $sender = $this->fromPhone;
        }
        $start = microtime(true);
        $message = $this->client->messages->create($recipient, [
            "from" => $sender,
            "body" => $message,
            "statusCallback" => env('APP_URL') . "/sms/callback"
        ]);

        $end = microtime(true);
        Log::info("API: Message to " . $recipient . " took " . ($end - $start) . "ms");

        return $message;
    }

    /**
     * getMessageStatus
     *
     * @param string $sid
     *
     * @return obj status
     */
    public function getMessage($sid)
    {
        try {
            return $this->client->messages($sid)->fetch();
        } catch (\Twilio\Exceptions\RestException $e) {
            if ($e->getStatusCode() === 404) {
                Log::notice("TwilioService::getMessage({$sid}) Exception " . $e->getStatusCode() . ": " . $e->getMessage() . " (" . $e->getCode() . ")");
            } else {
                Log::alert("TwilioService::getMessage({$sid}) Exception " . $e->getStatusCode() . ": " . $e->getMessage() . " (" . $e->getCode() . ")");
            }
            return $e;
        }
    }
}
