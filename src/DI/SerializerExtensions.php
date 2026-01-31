<?php

declare(strict_types=1);

namespace Lsr\Serializer\DI;

use Lsr\Serializer\Mapper;
use Lsr\Serializer\Normalizer\DateTimeNormalizer;
use Lsr\Serializer\SerializerHelper;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\PropertyInfo\Extractor\ConstructorExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\Extractor\SerializerExtractor;
use Symfony\Component\PropertyInfo\PropertyAccessExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyDescriptionExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\PropertyInitializableExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * @property-read object{
 *     extractors: list<class-string<object>>,
 *     normalizers: list<class-string<object>>,
 *     denormalizers: list<class-string<object>>,
 *     encoders: list<class-string<object>>,
 *     extraExtractors: list<class-string<object>>,
 *     extraNormalizers: list<class-string<object>>,
 *     extraDenormalizers: list<class-string<object>>,
 *     extraEncoders: list<class-string<object>>,
 *     context: object{
 *          common: object,
 *          encoder: object,
 *          serializer: object,
 *          normalizer: object,
 *          denormalizer: object,
 *     }
 * } $config
 */
class SerializerExtensions extends CompilerExtension
{
    /** @var ServiceDefinition[] */
    private array $encoders = [];

    /** @var ServiceDefinition[] */
    private array $normalizers = [];

    /** @var ServiceDefinition[] */
    private array $typeExtractors = [];
    /** @var ServiceDefinition[] */
    private array $descriptionExtractors = [];
    /** @var ServiceDefinition[] */
    private array $accessExtractors = [];
    /** @var ServiceDefinition[] */
    private array $initializableExtractors = [];
    /** @var ServiceDefinition[] */
    private array $listExtractors = [];

    public function getConfigSchema(): Schema
    {
        return Expect::structure(
            [
                'extractors' => Expect::listOf('string')->default([
                    ReflectionExtractor::class,
                    PhpDocExtractor::class,
                    SerializerExtractor::class,
                    ConstructorExtractor::class,
                ]),
                'extraExtractors' => Expect::listOf('string')->default([]),
                'normalizers' => Expect::listOf('string')->default([
                    DateTimeNormalizer::class,
                    BackedEnumNormalizer::class,
                    JsonSerializableNormalizer::class,
                    ObjectNormalizer::class,
                ]),
                'extraNormalizers' => Expect::listOf('string')->default([]),
                'denormalizers' => Expect::listOf('string')->default([
                    ArrayDenormalizer::class,
                ]),
                'extraDenormalizers' => Expect::listOf('string')->default([]),
                'encoders' => Expect::listOf('string')->default([
                    JsonEncoder::class,
                    XmlEncoder::class,
                    CsvEncoder::class,
                ]),
                'extraEncoders' => Expect::listOf('string')->default([]),
                'context' => Expect::structure([
                    'common' => Expect::structure([])->default([]),
                    'encoder' => Expect::structure([])->default([
                        JsonDecode::ASSOCIATIVE => true,
                        JsonEncode::OPTIONS => JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES
                            | JSON_PRESERVE_ZERO_FRACTION
                            | JSON_THROW_ON_ERROR
                            | JSON_INVALID_UTF8_SUBSTITUTE,
                    ]),
                    'serializer' => Expect::structure([])->default([

                    ]),
                    'normalizer' => Expect::structure([])->default([
                        AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => [
                            SerializerHelper::class,
                            'handleCircularReference',
                        ],
                    ]),
                ])->default([
                    'common' => [],
                    'encoder' => [
                        JsonDecode::ASSOCIATIVE => true,
                        JsonEncode::OPTIONS => JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES
                            | JSON_PRESERVE_ZERO_FRACTION
                            | JSON_THROW_ON_ERROR
                            | JSON_INVALID_UTF8_SUBSTITUTE,
                    ],
                    'serializer' => [],
                    'normalizer' => [
                        AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => [
                            SerializerHelper::class,
                            'handleCircularReference',
                        ],
                    ],
                    'denormalizer' => [],
                ]),
            ]
        );
    }

    /**
     * @throws ReflectionException
     */
    public function loadConfiguration(): void
    {
        $builder = $this->getContainerBuilder();

        $this->loadExtractors();

        $propertyInfo = $builder->addDefinition($this->prefix('propertyInfo'))
            ->setType(PropertyInfoExtractor::class)
            ->setFactory(
                PropertyInfoExtractor::class,
                [
                    'listExtractors' => $this->listExtractors,
                    'typeExtractors' => $this->typeExtractors,
                    'descriptionExtractors' => $this->descriptionExtractors,
                    'accessExtractors' => $this->accessExtractors,
                    'initializableExtractors' => $this->initializableExtractors,
                ],
            );

        $attributeLoader = $builder->addDefinition($this->prefix('attributeLoader'))
            ->setType(AttributeLoader::class)
            ->setFactory(AttributeLoader::class)
            ->setTags(['lsr', 'serializer', 'symfony']);

        $classMetadataFactory = $builder->addDefinition($this->prefix('classMetadataFactory'))
            ->setType(ClassMetadataFactory::class)
            ->setFactory(ClassMetadataFactory::class, [
                'loaders' => [$attributeLoader],
            ])
            ->setTags(['lsr', 'serializer', 'symfony']);

        $nameConverter = $builder->addDefinition($this->prefix('nameConverter'))
            ->setType(MetadataAwareNameConverter::class)
            ->setFactory(MetadataAwareNameConverter::class)
            ->setTags(['lsr', 'serializer', 'symfony']);


        $this->loadNormalizers();
        $this->loadDenormalizers();
        $this->loadEncoders();

        $serializer = $builder->addDefinition($this->prefix('serializer'))
            ->setType(Serializer::class)
            ->setFactory(Serializer::class, [
                $this->normalizers,
                $this->encoders,
            ])
            ->setTags(['lsr', 'serializer', 'symfony']);

        $mapper = $builder->addDefinition('mapper')
            ->setType(Mapper::class)
            ->setFactory(Mapper::class, [$serializer])
            ->setTags(['lsr', 'serializer']);
    }

    /**
     * @throws ReflectionException
     */
    private function loadExtractors(): void
    {
        $builder = $this->getContainerBuilder();

        $extractors = array_merge(
            $this->config->extractors,
            $this->config->extraExtractors,
        );

        foreach ($extractors as $extractor) {
            $name = lcfirst(new ReflectionClass($extractor)->getShortName());
            $extractorDefinition = $builder->addDefinition($this->prefix('extractor.' . $name))
                ->setType($extractor)
                ->setFactory($extractor)
                ->setAutowired(false)
                ->setTags(['lsr', 'serializer', 'symfony', 'extractor']);

            if (is_a($extractor, PropertyListExtractorInterface::class, true)) {
                $this->listExtractors[] = $extractorDefinition;
            }
            if (is_a($extractor, PropertyTypeExtractorInterface::class, true)) {
                $this->typeExtractors[] = $extractorDefinition;
            }
            if (is_a($extractor, PropertyDescriptionExtractorInterface::class, true)) {
                $this->descriptionExtractors[] = $extractorDefinition;
            }
            if (is_a($extractor, PropertyAccessExtractorInterface::class, true)) {
                $this->accessExtractors[] = $extractorDefinition;
            }
            if (is_a($extractor, PropertyInitializableExtractorInterface::class, true)) {
                $this->initializableExtractors[] = $extractorDefinition;
            }
        }
    }

    /**
     * @throws ReflectionException
     */
    private function loadNormalizers(): void
    {
        $builder = $this->getContainerBuilder();

        $normalizerContext = array_merge(
            (array)$this->config->context->common,
            (array)$this->config->context->normalizer,
        );

        $normalizers = array_merge(
            $this->config->normalizers,
            $this->config->extraNormalizers,
        );

        foreach ($normalizers as $normalizer) {
            $name = lcfirst(new ReflectionClass($normalizer)->getShortName());
            $this->normalizers[] = $builder->addDefinition($this->prefix('normalizer.' . $name))
                ->setType($normalizer)
                ->setFactory($normalizer)
                ->setArgument('defaultContext', $normalizerContext)
                ->setAutowired(false)
                ->setTags(['lsr', 'serializer', 'symfony', 'normalizer']);
        }
    }

    /**
     * @throws ReflectionException
     */
    private function loadDenormalizers(): void
    {
        $builder = $this->getContainerBuilder();

        $normalizerContext = array_merge(
            (array)$this->config->context->common,
            (array)$this->config->context->denormalizer,
        );

        $denormalizers = array_merge(
            $this->config->denormalizers,
            $this->config->extraDenormalizers,
        );

        foreach ($denormalizers as $normalizer) {
            $name = lcfirst(new ReflectionClass($normalizer)->getShortName());
            $this->normalizers[] = $builder->addDefinition($this->prefix('normalizer.' . $name))
                ->setType($normalizer)
                ->setFactory($normalizer)
                ->setArgument('defaultContext', $normalizerContext)
                ->setAutowired(false)
                ->setTags(['lsr', 'serializer', 'symfony', 'normalizer']);
        }
    }

    /**
     * @throws ReflectionException
     */
    private function loadEncoders(): void
    {
        $builder = $this->getContainerBuilder();

        $encoderContext = array_merge(
            (array)$this->config->context->common,
            (array)$this->config->context->encoder,
        );

        $encoders = array_merge(
            $this->config->encoders,
            $this->config->extraEncoders,
        );

        foreach ($encoders as $encoder) {
            $name = lcfirst(new ReflectionClass($encoder)->getShortName());
            $this->encoders[] = $builder->addDefinition($this->prefix('encoder.' . $name))
                ->setType($encoder)
                ->setFactory($encoder)
                ->setArgument('defaultContext', $encoderContext)
                ->setAutowired(false)
                ->setTags(['lsr', 'serializer', 'symfony', 'encoder']);
        }
    }
}
