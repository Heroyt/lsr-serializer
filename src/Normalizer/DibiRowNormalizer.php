<?php

namespace Lsr\Serializer\Normalizer;

use Dibi\Row;
use ReflectionClass;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use function is_callable;

/**
 * @phpstan-type Context array{
 *     allow_extra_attributes?: bool,
 *     circular_reference_handler?: callable|null,
 *     circular_reference_limit?: int,
 *     ignored_attributes?: string[],
 *     max_depth_handler?: callable,
 *     exclude_from_cache_key?: string[],
 * }
 */
final class DibiRowNormalizer extends AbstractObjectNormalizer
{
    /**
     * @var Context
     */
    protected array $defaultContext = [
        self::ALLOW_EXTRA_ATTRIBUTES     => true,
        self::CIRCULAR_REFERENCE_HANDLER => null,
        self::CIRCULAR_REFERENCE_LIMIT   => 1,
        self::IGNORED_ATTRIBUTES         => [],
    ];

    /**
     * @param  Context  $defaultContext
     */
    public function __construct(
        ?ClassMetadataFactoryInterface       $classMetadataFactory = null,
        ?NameConverterInterface              $nameConverter = null,
        ?PropertyTypeExtractorInterface      $propertyTypeExtractor = null,
        ?ClassDiscriminatorResolverInterface $classDiscriminatorResolver = null,
        ?callable                            $objectClassResolver = null,
        array                                $defaultContext = [],
    ) {
        parent::__construct(
            $classMetadataFactory,
            $nameConverter,
            $propertyTypeExtractor,
            $classDiscriminatorResolver,
            $objectClassResolver,
            $defaultContext
        );

        if (
            /** @phpstan-ignore booleanAnd.alwaysFalse */
            isset($this->defaultContext[self::MAX_DEPTH_HANDLER])
            /** @phpstan-ignore booleanNot.alwaysFalse */
            && !is_callable($this->defaultContext[self::MAX_DEPTH_HANDLER])
        ) {
            throw new InvalidArgumentException(
                sprintf('The "%s" given in the default context is not callable.', self::MAX_DEPTH_HANDLER)
            );
        }

        $this->defaultContext[self::EXCLUDE_FROM_CACHE_KEY] = array_merge(
            $this->defaultContext[self::EXCLUDE_FROM_CACHE_KEY] ?? [],
            [self::CIRCULAR_REFERENCE_LIMIT_COUNTERS]
        );

        if ($classMetadataFactory) {
            $classDiscriminatorResolver ??= new ClassDiscriminatorFromClassMetadata($classMetadataFactory);
        }
        $this->classDiscriminatorResolver = $classDiscriminatorResolver;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
          Row::class => true,
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Row;
    }

    /**
     * @param  Context  $context
     * @return array<string>
     */
    protected function extractAttributes(object $object, ?string $format = null, array $context = []): array
    {
        assert($object instanceof Row, 'Invalid input object.');
        return array_keys($object->toArray());
    }

    /**
     * @param  Context  $context
     */
    protected function getAttributeValue(
        object  $object,
        string  $attribute,
        ?string $format = null,
        array   $context = []
    ): mixed
    {
        assert($object instanceof Row, 'Invalid input object.');
        return $object->{$attribute};
    }

    /**
     * @param  array<string,mixed>  $context
     */
    protected function setAttributeValue(
        object  $object,
        string  $attribute,
        mixed   $value,
        ?string $format = null,
        array   $context = []
    ): void
    {
        assert($object instanceof Row, 'Invalid input object.');
        $object->{$attribute} = $value;
    }

    /**
     * @template T of object
     * @param array<string,mixed> $data
     * @param class-string<T> $class
     * @param array<string,mixed> $context
     * @param ReflectionClass<T> $reflectionClass
     * @param string[]|bool $allowedAttributes
     * @param string|null $format
     * @return T
     */
    protected function instantiateObject(array &$data, string $class, array &$context, ReflectionClass $reflectionClass, array|bool $allowedAttributes, ?string $format = null): object
    {
        return new $class($data);
    }
}
