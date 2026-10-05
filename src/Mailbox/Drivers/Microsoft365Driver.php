<?php

namespace Laragear\Dte\Mailbox\Drivers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Laragear\Dte\Contracts\MailboxDriver;
use Laragear\Dte\Data\InboundEmailData;
use Laragear\Dte\Mailbox\XmlExtractor;
use Microsoft\Graph\Generated\Models\FileAttachment;
use Microsoft\Graph\Generated\Models\Message;
use Microsoft\Graph\Generated\Users\Item\Messages\MessagesRequestBuilderGetQueryParameters;
use Microsoft\Graph\Generated\Users\Item\Messages\MessagesRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\GraphServiceClient;
use function base64_decode;

/**
 * Fetches UNREAD DTE exchange emails via the Microsoft Graph API.
 *
 * Required: microsoft/microsoft-graph.
 */
class Microsoft365Driver implements MailboxDriver
{
    /**
     * Create a new Microsoft 365 Driver instance.
     */
    public function __construct(
        protected ConfigRepository $config,
        protected XmlExtractor $xmlExtractor,
        protected Microsoft365ClientFactory $clientFactory,
        protected ?GraphServiceClient $graphClient = null,
    ) {
        //
    }

    /**
     * Returns all unread DTE interchange emails from the Microsoft 365 mailbox.
     *
     * @return iterable<int, InboundEmailData>
     */
    public function unread(): iterable
    {
        $client = $this->client();

        $requestConfiguration = new MessagesRequestBuilderGetRequestConfiguration;
        $requestConfiguration->queryParameters = new MessagesRequestBuilderGetQueryParameters;
        $requestConfiguration->queryParameters->filter = 'isRead eq false';
        $requestConfiguration->queryParameters->top = $this->config->get('dte.mailbox.batch_size', 50);
        $requestConfiguration->queryParameters->expand = ['attachments'];

        $messages = $client
            ->me()
            ->messages()
            ->get($requestConfiguration)
            ->wait()
            ->getValue() ?? [];

        foreach ($messages as $message) {
            yield $this->parseMessage($message);
        }
    }

    /**
     * Mark a previously fetched message as read.
     */
    public function markAsRead(InboundEmailData $email): void
    {
        $client = $this->client();

        $requestConfiguration = new MessagesRequestBuilderGetRequestConfiguration;
        $requestConfiguration->queryParameters = new MessagesRequestBuilderGetQueryParameters;
        $requestConfiguration->queryParameters->filter = "internetMessageId eq '".addslashes($email->messageId)."'";
        $requestConfiguration->queryParameters->top = 1;

        $messages = $client
            ->me()
            ->messages()
            ->get($requestConfiguration)
            ->wait()
            ->getValue() ?? [];

        foreach ($messages as $message) {
            $update = new Message;
            $update->setIsRead(true);
            $client->me()->messages()->byMessageId($message->getId())->patch($update)->wait();
        }
    }

    /**
     * Build an authenticated Microsoft Graph service client.
     */
    protected function client(): GraphServiceClient
    {
        if ($this->graphClient !== null) {
            return $this->graphClient;
        }

        return $this->graphClient = $this->clientFactory->make(
            $this->config->get('dte.mailbox.drivers.microsoft', []),
        );
    }

    /**
     * Parse a Microsoft Graph message into an InboundEmailData DTO.
     */
    protected function parseMessage(Message $message): InboundEmailData
    {
        $xmlAttachment = '';
        foreach ($message->getAttachments() ?? [] as $attachment) {
            if (!$attachment instanceof FileAttachment) {
                continue;
            }

            $mimeType = $attachment->getContentType() ?? '';

            if (str_contains($mimeType, 'xml')) {
                $decoded = base64_decode($attachment->getContentBytes() ?? '', true) ?: '';
                $xmlAttachment = $this->xmlExtractor->extractFromString($decoded);
                break;
            }
        }

        return new InboundEmailData(
            messageId: $message->getInternetMessageId() ?? $message->getId() ?? '',
            sender: $message->getFrom()?->getEmailAddress()?->getAddress() ?? '',
            subject: $message->getSubject() ?? '',
            xmlAttachment: $xmlAttachment,
        );
    }
}
