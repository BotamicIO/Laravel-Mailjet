<?php

/*
 * This file is part of Laravel Mailjet.
 *
 * (c) Brian Faust <hello@brianfaust.me>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace BrianFaust\Mailjet;

use Swift_Encoding;
use Swift_Mime_Message;
use GuzzleHttp\ClientInterface;
use Illuminate\Mail\Transport\Transport;

class MailjetTransport extends Transport
{
    /**
     * Guzzle client instance.
     *
     * @var \GuzzleHttp\ClientInterface
     */
    protected $client;

    /**
     * The Mailjet public API key.
     *
     * @var string
     */
    protected $publicKey;

    /**
     * The Mailjet private API key.
     *
     * @var string
     */
    protected $privateKey;

    /**
     * Create a new Mailjet transport instance.
     *
     * @param \GuzzleHttp\ClientInterface $client
     * @param string                      $publicKey
     * @param string                      $privateKey
     */
    public function __construct(ClientInterface $client, $publicKey, $privateKey)
    {
        $this->client = $client;
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Swift_Mime_Message $message, &$failedRecipients = null): bool
    {
        $this->beforeSendPerformed($message);

        $from = $this->getFrom($message);
        $recipients = $this->getRecipients($message);

        $message->setBcc([]);

        $options = [
            'auth'    => [$this->publicKey, $this->privateKey],
            'headers' => [
                'Headers' => ['Reply-To' => $this->getReplyTo($message)],
            ],
            'json' => [
                'FromEmail'  => $from['email'],
                'FromName'   => $from['name'],
                'Subject'    => $message->getSubject(),
                'Text-part'  => $message->toString(),
                'Html-part'  => $message->getBody(),
                'Recipients' => $recipients,
            ],
        ];

        if ($attachments = $message->getChildren()) {
            $options['json']['Attachments'] = array_map(function ($attachment) {
                return [
                    'Content-type' => $attachment->getContentType(),
                    'Filename'     => $attachment->getFileName(),
                    'content'      => Swift_Encoding::getBase64Encoding()->encodeString($attachment->getBody()),
                ];
            }, $attachments);
        }

        return (bool) $this->client->post('https://api.mailjet.com/v3/send', $options);
    }

    /**
     * Get all the addresses this message should be sent to.
     *
     * @param \Swift_Mime_Message $message
     *
     * @return array
     */
    protected function getRecipients(Swift_Mime_Message $message): array
    {
        $to = [];

        if ($message->getTo()) {
            $to = array_merge($to, $message->getTo());
        }

        if ($message->getCc()) {
            $to = array_merge($to, $message->getCc());
        }

        if ($message->getBcc()) {
            $to = array_merge($to, $message->getBcc());
        }

        $recipients = [];
        foreach ($to as $address => $name) {
            $recipients[] = ['Email' => $address, 'Name' => $name];
        }

        return $recipients;
    }

    /**
     * Get the "from" contacts in the format required by Mailjet.
     *
     * @param Swift_Mime_Message $message
     *
     * @return array
     */
    protected function getFrom(Swift_Mime_Message $message): array
    {
        return array_map(function ($email, $name) {
            return compact('name', 'email');
        }, array_keys($message->getFrom()), $message->getFrom())[0];
    }

    /**
     * Get the 'reply_to' headers and format as required by Mailjet.
     *
     * @param Swift_Mime_Message $message
     *
     * @return string
     */
    protected function getReplyTo(Swift_Mime_Message $message): ?string
    {
        if (is_array($message->getReplyTo())) {
            return current($message->getReplyTo()).' <'.key($message->getReplyTo()).'>';
        }
    }

    /**
     * Get the public API key being used by the transport.
     *
     * @return string
     */
    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * Set the public API key being used by the transport.
     *
     * @param string $key
     *
     * @return string
     */
    public function setPublicKey($publicKey): bool
    {
        return $this->publicKey = $publicKey;
    }

    /**
     * Get the private API key being used by the transport.
     *
     * @return string
     */
    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }

    /**
     * Set the private API key being used by the transport.
     *
     * @param string $key
     *
     * @return string
     */
    public function setPrivateKey($privateKey): bool
    {
        return $this->publicKey = $publicKey;
    }
}
