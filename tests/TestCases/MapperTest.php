<?php

declare(strict_types=1);

namespace TestCases;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Dibi\Row;
use Lsr\Serializer\Mapper;
use Lsr\Serializer\Normalizer\DateTimeNormalizer;
use Lsr\Serializer\Normalizer\DibiRowNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class MapperTest extends TestCase
{
    private Mapper $mapper;

    public function testMapArray(): void
    {
        $data = [
          'string'         => 'string',
          'int'            => 123,
          'float'          => 1.23,
          'bool'           => true,
          'array'          => [1, 2, 3],
          'object'         => (object) ['foo' => 'bar'],
          'datetime'       => new DateTimeImmutable(),
          'datetimeString' => date('c'),
          'datetimeArray'  => [
            'date'     => date('Y-m-d\TH:i:s'),
            'timezone' => (new DateTimeZone('Europe/Prague'))->getName(),
          ],
          'child'          => [
            'string'   => 'child',
            'nullable' => null,
          ],
          'child2'         => [
            'string'   => 'child2',
            'nullable' => 'null',
          ],
          'nullableChild'  => null,
        ];

        $object = $this->mapper->map($data, MappedClass::class);

        self::assertInstanceOf(MappedClass::class, $object);
        self::assertEquals('string', $object->string);
        self::assertEquals(123, $object->int);
        self::assertEquals(1.23, $object->float);
        self::assertTrue($object->bool);
        self::assertEquals([1, 2, 3], $object->array);
        self::assertEquals((object) ['foo' => 'bar'], $object->object);
        self::assertInstanceOf(DateTimeInterface::class, $object->datetime);
        self::assertEquals($data['datetime']->format('c'), $object->datetime->format('c'));
        self::assertInstanceOf(DateTimeInterface::class, $object->datetimeString);
        self::assertEquals($data['datetimeString'], $object->datetimeString->format('c'));
        self::assertInstanceOf(DateTimeInterface::class, $object->datetimeArray);
        self::assertEquals($data['datetimeArray']['date'], $object->datetimeArray->format('Y-m-d\TH:i:s'));
        self::assertEquals(
            (new DateTimeZone($data['datetimeArray']['timezone']))->getName(),
            $object->datetimeArray->getTimezone()->getName()
        );
        self::assertInstanceOf(MappedClassB::class, $object->child);
        self::assertEquals('child', $object->child->string);
        self::assertNull($object->child->nullable);
        self::assertInstanceOf(MappedClassB::class, $object->child2);
        self::assertEquals('child2', $object->child2->string);
        self::assertEquals('null', $object->child2->nullable);
        self::assertNull($object->nullableChild);
    }

    public function testMapDibiRow(): void
    {
        $data = [
          'string'         => 'string',
          'int'            => 123,
          'float'          => 1.23,
          'bool'           => true,
          'array'          => [1, 2, 3],
          'object'         => (object) ['foo' => 'bar'],
          'datetime'       => new DateTimeImmutable(),
          'datetimeString' => date('c'),
          'datetimeArray'  => [
            'date'     => date('Y-m-d\TH:i:s'),
            'timezone' => (new DateTimeZone('Europe/Prague'))->getName(),
          ],
          'child'          => [
            'string'   => 'child',
            'nullable' => null,
          ],
          'child2'         => [
            'string'   => 'child2',
            'nullable' => 'null',
          ],
          'nullableChild'  => null,
        ];
        $row = new Row($data);

        $object = $this->mapper->map($row, MappedClass::class);

        self::assertInstanceOf(MappedClass::class, $object);
        self::assertEquals('string', $object->string);
        self::assertEquals(123, $object->int);
        self::assertEquals(1.23, $object->float);
        self::assertTrue($object->bool);
        self::assertEquals([1, 2, 3], $object->array);
        self::assertEquals((object) ['foo' => 'bar'], $object->object);
        self::assertInstanceOf(DateTimeInterface::class, $object->datetime);
        self::assertEquals($data['datetime']->format('c'), $object->datetime->format('c'));
        self::assertInstanceOf(DateTimeInterface::class, $object->datetimeString);
        self::assertEquals($data['datetimeString'], $object->datetimeString->format('c'));
        self::assertInstanceOf(DateTimeInterface::class, $object->datetimeArray);
        self::assertEquals($data['datetimeArray']['date'], $object->datetimeArray->format('Y-m-d\TH:i:s'));
        self::assertEquals(
            (new DateTimeZone($data['datetimeArray']['timezone']))->getName(),
            $object->datetimeArray->getTimezone()->getName()
        );
        self::assertInstanceOf(MappedClassB::class, $object->child);
        self::assertEquals('child', $object->child->string);
        self::assertNull($object->child->nullable);
        self::assertInstanceOf(MappedClassB::class, $object->child2);
        self::assertEquals('child2', $object->child2->string);
        self::assertEquals('null', $object->child2->nullable);
        self::assertNull($object->nullableChild);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new Mapper(
            new Serializer(
                [
                    new ArrayDenormalizer(),
                    new DateTimeNormalizer(),
                    new DibiRowNormalizer(),
                    new BackedEnumNormalizer(),
                    new JsonSerializableNormalizer(),
                    new ObjectNormalizer(propertyTypeExtractor: new ReflectionExtractor(),),
                ]
            )
        );
    }
}

class MappedClass
{
    public string $string;
    public int $int;
    public float $float;
    public bool $bool;
    /** @var int[] */
    public array $array;
    public object $object;
    public DateTimeInterface $datetime;
    public DateTimeInterface $datetimeString;
    public DateTimeInterface $datetimeArray;
    public MappedClassB $child;
    public MappedClassB $child2;
    public ?MappedClassB $nullableChild;
}

class MappedClassB
{
    public string $string;
    public ?string $nullable;
}
