<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Storage;

use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 *
 * Reads back the JSON files this bundle writes. They are committed or edited by hand, so their shape
 * is checked rather than trusted.
 */
final class Json
{
    /**
     * @return array<mixed>
     */
    public static function decodeFile(Filesystem $filesystem, string $path): array
    {
        $data = json_decode($filesystem->readFile($path), true, flags: \JSON_THROW_ON_ERROR);

        return \is_array($data) ? $data : throw self::malformed($path, 'a JSON object');
    }

    /**
     * @param array<mixed> $data
     */
    public static function string(array $data, string $key): string
    {
        return \is_string($data[$key] ?? null) ? $data[$key] : throw self::malformed($key, 'a string');
    }

    /**
     * @param array<mixed> $data
     */
    public static function float(array $data, string $key): float
    {
        $value = $data[$key] ?? null;

        return \is_float($value) || \is_int($value) ? $value : throw self::malformed($key, 'a number');
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    public static function array(array $data, string $key): array
    {
        return \is_array($data[$key] ?? null) ? $data[$key] : throw self::malformed($key, 'an object or a list');
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<array<mixed>>
     */
    public static function objects(array $data, string $key): array
    {
        $objects = [];
        foreach (self::array($data, $key) as $object) {
            $objects[] = \is_array($object) ? $object : throw self::malformed($key, 'a list of objects');
        }

        return $objects;
    }

    private static function malformed(string $key, string $expected): \UnexpectedValueException
    {
        return new \UnexpectedValueException(\sprintf('Malformed recall file: "%s" should be %s.', $key, $expected));
    }
}
