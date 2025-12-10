<?php

namespace App\MessageSerializer;

use App\Message\UserUpdatedEvent;
use App\Message\UserDeletedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Serializer\SerializerInterface as SymfonySerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserMessageSerializer implements SerializerInterface
{
    private const TYPE_MAPPING = [
        UserUpdatedEvent::class => 'user_update',
        UserDeletedEvent::class => 'user_delete',
    ];

    public function __construct(
        private readonly SymfonySerializerInterface $serializer,
        private readonly LoggerInterface $logger,
        private readonly ValidatorInterface $validator,
    ) {}

    /**
     * Decode payload from RabbitMQ into an Envelope with the correct Event class.
     */
    public function decode(array $encodedEnvelope): Envelope
    {
        $headers = $encodedEnvelope['headers'] ?? [];
        $messageType = $headers['type'] ?? null;
        $body = $encodedEnvelope['body'] ?? '';

        // Find mapped class for given type
        $mappedClass = null;
        foreach (self::TYPE_MAPPING as $class => $type) {
            if ($type === $messageType) {
                $mappedClass = $class;
                break;
            }
        }

        if ($mappedClass === null) {
            $this->logger->notice('USER_MESSAGE_SERIALIZER :: Unrecognized message type', ['type' => $messageType]);
            throw new MessageDecodingFailedException('Unrecognized message type: ' . $messageType);
        }

        try {
            $message = $this->serializer->deserialize($body, $mappedClass, 'json');

            $violations = $this->validator->validate($message);
            if ($violations->count() > 0) {
                $errors = [];
                foreach ($violations as $violation) {
                    $errors[$violation->getPropertyPath()][] = $violation->getMessage();
                }
                $this->logger->error(
                    'USER_MESSAGE_SERIALIZER :: Invalid message data',
                    ['errors' => $errors, 'message_class' => $mappedClass]
                );
                throw new MessageDecodingFailedException('Invalid message data for ' . $mappedClass);
            }

            // For now we ignore stamps; you can enrich this later if needed
            return new Envelope($message);
        } catch (\Throwable $e) {
            $this->logger->error(
                'USER_MESSAGE_SERIALIZER :: Failed to decode message',
                ['error' => $e->getMessage(), 'body' => $body, 'type' => $messageType]
            );
            throw new MessageDecodingFailedException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Encode an Envelope into AMQP payload (if fund-manager ever publishes).
     */
    public function encode(Envelope $envelope): array
    {
        $message = $envelope->getMessage();
        $class = $message::class;

        $type = self::TYPE_MAPPING[$class] ?? null;
        if ($type === null) {
            $this->logger->error(
                'USER_MESSAGE_SERIALIZER :: Invalid message type during encoding',
                ['class' => $class]
            );
            throw new MessageDecodingFailedException('Invalid message type: ' . $class);
        }

        $encodedMessage = $this->serializer->serialize($message, 'json');

        // We’re not encoding stamps here for simplicity
        return [
            'body' => $encodedMessage,
            'headers' => [
                'type' => $type,
            ],
        ];
    }
}
