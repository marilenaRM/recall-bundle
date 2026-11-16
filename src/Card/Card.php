<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Card;

use MarilenaRM\RecallBundle\Fact\Fact;
use MarilenaRM\RecallBundle\Fact\FactKind;
use MarilenaRM\RecallBundle\Storage\Json;

final readonly class Card
{
    public function __construct(
        public string $id,
        public string $factId,
        public FactKind $kind,
        public string $question,
        public string $answer,
        public string $source,
    ) {
    }

    public static function fromDraft(Fact $fact, CardDraft $draft): self
    {
        return new self(
            id: substr(hash('xxh128', $fact->id.$fact->fingerprint.$draft->question), 0, 16),
            factId: $fact->id,
            kind: $fact->kind,
            question: $draft->question,
            answer: $draft->answer,
            source: $fact->source,
        );
    }

    public function withSource(string $source): self
    {
        return new self($this->id, $this->factId, $this->kind, $this->question, $this->answer, $source);
    }

    /**
     * @return array{id: string, fact_id: string, kind: string, question: string, answer: string, source: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'fact_id' => $this->factId,
            'kind' => $this->kind->value,
            'question' => $this->question,
            'answer' => $this->answer,
            'source' => $this->source,
        ];
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Json::string($data, 'id'),
            factId: Json::string($data, 'fact_id'),
            kind: FactKind::from(Json::string($data, 'kind')),
            question: Json::string($data, 'question'),
            answer: Json::string($data, 'answer'),
            source: Json::string($data, 'source'),
        );
    }
}
