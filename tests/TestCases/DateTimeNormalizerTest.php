<?php
declare(strict_types=1);

namespace TestCases;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Lsr\Serializer\Normalizer\DateTimeNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Generator;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

class DateTimeNormalizerTest extends TestCase
{

    public static function getTestData() : Generator {
        yield [
            '2024-01-01 00:00:00',
            new DateTimeImmutable('2024-01-01 00:00:00'),
            DateTimeImmutable::class,
            [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d H:i:s'],
        ];
        yield [
            strtotime('2024-01-01 00:00:00'),
            new DateTimeImmutable('2024-01-01 00:00:00'),
            DateTime::class,
            [DateTimeNormalizer::FORMAT_KEY => 'U', DateTimeNormalizer::CAST_KEY => 'int'],
        ];
        yield [
            (float) strtotime('2024-01-01 00:00:00'),
            new DateTimeImmutable('2024-01-01 00:00:00'),
            DateTime::class,
            [DateTimeNormalizer::FORMAT_KEY => 'U.u', DateTimeNormalizer::CAST_KEY => 'float'],
        ];
        $timezone = new DateTimeZone('UTC');
        yield [
            [
                'date'     => '2024-01-01T00:00:00+00:00',
                'timezone' => $timezone->getName(),
            ],
            new DateTimeImmutable('2024-01-01 00:00:00', $timezone),
            DateTimeImmutable::class,
            [DateTimeNormalizer::CAST_KEY => 'array'],
        ];
    }

    public static function getDenormalizationErrorData() : Generator {
        yield [
            'abcdef',
        ];
        yield [
            [],
        ];
        yield [
            ['date' => 123],
        ];
        yield [
            ['date' => ''],
        ];
        yield [
            strtotime('2024-01-01 00:00:00'),
            // With no context
        ];
    }

    #[DataProvider('getDenormalizationErrorData')]
    public function testDenormalizeInvalid(mixed $normalized, array $context = []) : void {
        $normalizer = new DateTimeNormalizer();

        $this->expectException(NotNormalizableValueException::class);
        $normalizer->denormalize($normalized, DateTimeImmutable::class, context: $context);
    }

    #[DataProvider('getTestData')]
    public function testDenormalize(
        mixed             $normalized,
        DateTimeInterface $denormalized,
        string            $class,
        array             $context = []
    ) : void {
        $normalizer = new DateTimeNormalizer();

        $this->assertTrue($normalizer->supportsDenormalization($normalized, $class));
        $date = $normalizer->denormalize($normalized, $class, context: $context);
        $this->assertInstanceOf($class, $date);
        $this->assertEquals($denormalized->format('c'), $date->format('c'));
    }

    public function testDenormalizeFromObject() : void {
        $normalizer = new DateTimeNormalizer();
        $date = new DateTimeImmutable('2024-01-01 00:00:00');
        $this->assertTrue($normalizer->supportsDenormalization($date, DateTimeImmutable::class));
        $date1 = $normalizer->denormalize($date, DateTimeImmutable::class);
        $this->assertInstanceOf(DateTimeImmutable::class, $date1);
        $this->assertNotSame($date, $date1);
        $this->assertEquals($date->format('c'), $date1->format('c'));
    }

    #[DataProvider('getTestData')]
    public function testNormalize(
        mixed             $normalized,
        DateTimeInterface $denormalized,
        string            $class,
        array             $context = []
    ) : void {
        $normalizer = new DateTimeNormalizer();

        $this->assertTrue($normalizer->supportsNormalization($denormalized));
        $date = $normalizer->normalize($denormalized, context: $context);
        $this->assertSame($normalized, $date);
    }
}
