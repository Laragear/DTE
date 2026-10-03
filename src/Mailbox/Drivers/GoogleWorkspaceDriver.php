<?php

namespace Laragear\Dte\Mailbox\Drivers;

use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Google\Service\Gmail\ModifyMessageRequest;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Laragear\Dte\Contracts\MailboxDriverInterface;
use Laragear\Dte\Data\InboundEmailData;
use Laragear\Dte\Mailbox\XmlExtractor;
use function base64_decode;
use function strtr;

/**
 * Fetches UNREAD DTE exchange emails via the Gmail REST API.
 *
 * Required: google/apiclient with the Gmail service.
 */
class GoogleWorkspaceDriver implements MailboxDriverInterface
{
    /**
     * Create a new Google Workspace Driver instance.
     */
    public function __construct(
        protected ConfigRepository $config,
        protected Client $client,
        protected XmlExtractor $xmlExtractor,
        protected GoogleWorkspaceClientFactory $clientFactory,
        protected ?Gmail $gmail = null,
    ) {
        //
    }

    /**
     * Returns all unread DTE interchange emails from the Gmail inbox.
     *
     * @return iterable<int, InboundEmailData>
     */
    public function unread(): iterable
    {
        $gmail = $this->gmail();

        $messages = $gmail->users_messages->listUsersMessages('me', [
            'q' => 'is:unread',
            'maxResults' => $this->config->get('dte.mailbox.batch_size', 50),
        ]);

        foreach ($messages->getMessages() ?? [] as $stub) {
            /** @var Message $message */
            $message = $gmail->users_messages->get('me', $stub->getId(), [
                'format' => 'full',
            ]);

            yield $this->parseMessage($message);
        }
    }

    /**
     * Mark a previously fetched message as read.
     */
    public function markAsRead(InboundEmailData $email): void
    {
        $gmail = $this->gmail();

        $messages = $gmail->users_messages->listUsersMessages('me', [
            'q' => 'rfc822msgid:'.$email->messageId,
            'maxResults' => 1,
        ]);

        foreach ($messages->getMessages() ?? [] as $message) {
            $request = new ModifyMessageRequest;
            $request->setRemoveLabelIds(['UNREAD']);
            $gmail->users_messages->modify('me', $message->getId(), $request);
        }
    }

    /**
     * Build an authenticated Gmail service instance.
     */
    protected function gmail(): Gmail
    {
        if ($this->gmail !== null) {
            return $this->gmail;
        }

        return $this->gmail = $this->clientFactory->make(
            $this->config->get('dte.mailbox.drivers.google', []),
            $this->client,
        );
    }

    /**
     * Parse a Gmail API message into an InboundEmailData DTO.
     */
    protected function parseMessage(Message $message): InboundEmailData
    {
        $headers = [];

        foreach ($message->getPayload()->getHeaders() as $header) {
            $headers[$header->getName()] = $header->getValue();
        }

        $xmlAttachment = '';

        foreach ($message->getPayload()->getParts() ?? [] as $part) {
            $mimeType = $part->getMimeType();

            if ($mimeType === 'text/xml' || $mimeType === 'application/xml') {
                $data = strtr($part->getBody()->getData() ?? '', ['-' => '+', '_' => '/']);
                $decoded = base64_decode($data, true) ?: '';
                $xmlAttachment = $this->xmlExtractor->extractFromString($decoded);
                break;
            }
        }

        return new InboundEmailData(
            messageId: $headers['Message-ID'] ?? $message->getId(),
            sender: $headers['From'] ?? '',
            subject: $headers['Subject'] ?? '',
            xmlAttachment: $xmlAttachment,
        );
    }
}
