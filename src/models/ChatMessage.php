<?php

declare(strict_types=1);

final class ChatMessage
{
    public function __construct(
        private int $id,
        private int $threadId,
        private int $senderUserId,
        private string $body,
        private DateTimeImmutable $createdAt
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getThreadId(): int
    {
        return $this->threadId;
    }

    public function getSenderUserId(): int
    {
        return $this->senderUserId;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}


