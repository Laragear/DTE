<?php

namespace Laragear\Dte\Mailbox\Drivers;

use Aws\S3\S3Client;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Laragear\Dte\Contracts\MailboxDriverInterface;
use Laragear\Dte\Data\InboundEmailData;
use Laragear\Dte\Mailbox\XmlExtractor;
use function preg_match;

/**
 * Processes inbound DTE emails routed through AWS SES via S3 bucket fetch.
 *
 * Required: aws/aws-sdk-php.
 */
class AwsSesDriver implements MailboxDriverInterface
{
    /**
     * Create a new AWS SES Driver instance.
     */
    public function __construct(
        protected ConfigRepository $config,
        protected S3Client $s3,
        protected XmlExtractor $xmlExtractor,
    ) {
        //
    }

    /**
     * Fetch unread emails from the configured S3 bucket (SES stores them there).
     *
     * @return iterable<int, InboundEmailData>
     */
    public function unread(): iterable
    {
        $config = $this->driverConfig();
        $bucket = $config['bucket'] ?? '';
        $prefix = $config['prefix'] ?? 'emails/unread/';

        $objects = $this->s3->listObjectsV2([
            'Bucket' => $bucket,
            'Prefix' => $prefix,
            'MaxKeys' => $this->config->get('dte.mailbox.batch_size', 50),
        ]);

        foreach ($objects['Contents'] ?? [] as $object) {
            $key = $object['Key'];

            $result = $this->s3->getObject([
                'Bucket' => $bucket,
                'Key' => $key,
            ]);

            $rawEmail = (string) $result['Body'];

            yield $this->parseRawEmail($rawEmail, $key);
        }
    }

    /**
     * Mark a message as read by moving its S3 object to the read prefix.
     */
    public function markAsRead(InboundEmailData $email): void
    {
        $config = $this->driverConfig();
        $bucket = $config['bucket'] ?? '';
        $unreadPrefix = $config['prefix'] ?? 'emails/unread/';
        $readPrefix = $config['read_prefix'] ?? 'emails/read/';

        $sourceKey = $unreadPrefix.basename($email->messageId);
        $destKey = $readPrefix.basename($email->messageId);

        $this->s3->copyObject([
            'Bucket' => $bucket,
            'CopySource' => $bucket.'/'.$sourceKey,
            'Key' => $destKey,
        ]);

        $this->s3->deleteObject([
            'Bucket' => $bucket,
            'Key' => $sourceKey,
        ]);
    }

    /**
     * Parse a raw MIME email string into an InboundEmailData DTO.
     */
    protected function parseRawEmail(string $raw, string $key): InboundEmailData
    {
        $messageId = '';

        if (preg_match('/^Message-ID:\s*(.+)$/im', $raw, $matches)) {
            $messageId = trim($matches[1]);
        }

        $from = '';

        if (preg_match('/^From:\s*(.+)$/im', $raw, $matches)) {
            $from = trim($matches[1]);
        }

        $subject = '';

        if (preg_match('/^Subject:\s*(.+)$/im', $raw, $matches)) {
            $subject = trim($matches[1]);
        }

        return new InboundEmailData(
            messageId: $messageId ?: $key,
            sender: $from,
            subject: $subject,
            xmlAttachment: $this->xmlExtractor->extractFromRaw($raw),
        );
    }

    /**
     * Return the AWS SES driver configuration.
     *
     * @return array<string, mixed>
     */
    protected function driverConfig(): array
    {
        return $this->config->get('dte.mailbox.drivers.aws_ses', []);
    }
}
