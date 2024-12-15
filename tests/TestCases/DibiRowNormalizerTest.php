<?php
declare(strict_types=1);

namespace TestCases;

use Dibi\Row;
use Lsr\Serializer\Normalizer\DibiRowNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DibiRowNormalizerTest extends TestCase
{

    public static function getData() : \Generator {
        $data = [
            'test' => 123,
            'foo' => 'bar',
        ];
        yield [
            $data,
            new Row($data),
        ];
    }

    #[DataProvider('getData')]
    public function testNormalize(
        array $normalized,
        Row $denormalized,
        array $context = [],
    ) : void {
        $normalizer = new DibiRowNormalizer();

        $this->assertTrue($normalizer->supportsNormalization($denormalized));
        $data = $normalizer->normalize($denormalized, context: $context);
        foreach ($normalized as $key => $value) {
            $this->assertSame($value, $data[$key]);
        }
    }

    #[DataProvider('getData')]
    public function testDenormalize(
        array $normalized,
        Row $denormalized,
        array $context = [],
    ) : void {
        $normalizer = new DibiRowNormalizer();

        $this->assertTrue($normalizer->supportsDenormalization($normalized, Row::class));
        $row = $normalizer->denormalize($normalized, Row::class, context: $context);
        $this->assertInstanceOf(Row::class, $row);
        $this->assertEquals($denormalized->toArray(), $row->toArray());
    }
}
