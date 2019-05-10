<?php

namespace LeNats\Services;

//use Doctrine\Common\Annotations\AnnotationReader;
//use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
//use Symfony\Component\Serializer\Encoder\JsonDecode;
//use Symfony\Component\Serializer\Encoder\JsonEncode;
//use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
//use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
//use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
//use Symfony\Component\Serializer\SerializerInterface;

//class Serializer implements SerializerInterface
class Serializer
{
    /**
//     * @var \Symfony\Component\Serializer\Serializer
     * @var \JMS\Serializer\SerializerInterface
     */
    private $serializer;

    public function __construct(\JMS\Serializer\SerializerInterface $serializer)
    {
        $this->serializer = $serializer;

//        $this->serializer = new \Symfony\Component\Serializer\Serializer(
//            [
//                new ObjectNormalizer(
//                    new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader())),
//                    null,
//                    null,
//                    new PhpDocExtractor()
//                ),
//            ],
//            [new JsonDecode(), new JsonEncode()]
//        );
    }

    /**
     * Serializes data in the appropriate format.
     *
     * @param mixed $data Any data
     * @param string $format Format name
     * @param array $context Options normalizers/encoders have access to
     *
     * @return string
     */
    public
    function serialize(
        $data,
        $format
    ) {
        return $this->serializer->serialize($data, $format);
    }

    /**
     * Deserializes data into the given type.
     *
     * @param mixed $data
     * @param string $type
     * @param string $format
     * @param array $context
     *
     * @return object
     */
    public
    function deserialize(
        $data,
        $type,
        $format,
        array $context = []
    ) {
        return $this->serializer->deserialize($data, $type, $format);
    }
}
