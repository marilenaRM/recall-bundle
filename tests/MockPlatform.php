<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Tests;

use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class MockPlatform
{
    /**
     * @param \Closure(string, array<mixed>): string $respond receives every text of the request payload, one per line, and the call options
     */
    public static function create(\Closure $respond): Platform
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new PlatformSubscriber());

        return MockPlatformFactory::createPlatform(
            static function ($model, array|string|object $payload, array $options) use ($respond): string {
                if (\is_string($payload)) {
                    return $respond($payload, $options);
                }

                $texts = [];
                array_walk_recursive($payload, static function (mixed $value) use (&$texts): void {
                    if (\is_string($value)) {
                        $texts[] = $value;
                    }
                });

                return $respond(implode("\n", $texts), $options);
            },
            eventDispatcher: $dispatcher,
        );
    }

    /**
     * @param list<array{0: string, 1: string}> $cards question/answer pairs
     */
    public static function answering(array $cards): string
    {
        return json_encode(['cards' => array_map(
            static fn (array $card): array => ['question' => $card[0], 'answer' => $card[1]],
            $cards,
        )], \JSON_THROW_ON_ERROR);
    }
}
