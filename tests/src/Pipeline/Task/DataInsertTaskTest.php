<?php

declare(strict_types=1);

namespace Liquetsoft\Fias\Component\Tests\Pipeline\Task;

use InvalidArgumentException;
use Liquetsoft\Fias\Component\EntityDescriptor\EntityDescriptor;
use Liquetsoft\Fias\Component\EntityManager\EntityManager;
use Liquetsoft\Fias\Component\Exception\TaskException;
use Liquetsoft\Fias\Component\Parser\XmlParser;
use Liquetsoft\Fias\Component\Pipeline\State\ArrayState;
use Liquetsoft\Fias\Component\Pipeline\Task\DataInsertTask;
use Liquetsoft\Fias\Component\Pipeline\Task\Task;
use Liquetsoft\Fias\Component\Reader\XmlReader;
use Liquetsoft\Fias\Component\Serializer\FiasSerializer;
use Liquetsoft\Fias\Component\Storage\Storage;
use Liquetsoft\Fias\Component\Tests\BaseCase;
use SplFileInfo;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Тест для задачи, которая загружает данные из файла в БД.
 */
class DataInsertTaskTest extends BaseCase
{
    /**
     * Проверяет, что объект читает и записывает данные.
     */
    public function testRun()
    {
        $descriptor = $this->getMockBuilder(EntityDescriptor::class)->getMock();
        $descriptor->method('getReaderParams')->will($this->returnValue('/ActualStatuses/ActualStatus'));

        $entityManager = $this->getMockBuilder(EntityManager::class)->getMock();
        $entityManager->method('getDescriptorByInsertFile')->will($this->returnCallback(function ($file) use ($descriptor) {
            return $file === 'data.xml' ? $descriptor : null;
        }));
        $entityManager->method('getClassByDescriptor')->will($this->returnCallback(function ($testDescriptor) use ($descriptor) {
            return $testDescriptor === $descriptor ? DataInsertTaskObject::class : null;
        }));

        $insertedData = [];
        $storage = $this->getMockBuilder(Storage::class)->getMock();
        $storage->expects($this->once())->method('start');
        $storage->expects($this->once())->method('stop');
        $storage->method('supports')->will($this->returnCallback(function ($object) use (&$insertedData) {
            return $object->getActstatid() === 321;
        }));
        $storage->method('insert')->will($this->returnCallback(function ($object) use (&$insertedData) {
            $insertedData[] = $object->getActstatid();
        }));

        $file = new SplFileInfo(__DIR__ . '/_fixtures/data.xml');
        $state = new ArrayState;
        $state->setParameter(Task::FILES_TO_INSERT_PARAM, [$file->getPathname()]);

        $reader = new XmlReader;
        $reader->open($file, $descriptor);

        $task = new DataInsertTask($entityManager, new XmlParser($reader, new FiasSerializer), $storage);
        $task->run($state);
        $reader->close();

        $this->assertSame([321], $insertedData);
    }

    /**
     * Проверяет, что объект обработает исключение от сериализатора.
     */
    public function testRunDeserializeException()
    {
        $descriptor = $this->getMockBuilder(EntityDescriptor::class)->getMock();
        $descriptor->method('getReaderParams')->will($this->returnValue('/ActualStatuses/ActualStatus'));

        $entityManager = $this->getMockBuilder(EntityManager::class)->getMock();
        $entityManager->method('getDescriptorByInsertFile')->will($this->returnCallback(function ($file) use ($descriptor) {
            return $file === 'data.xml' ? $descriptor : null;
        }));
        $entityManager->method('getClassByDescriptor')->will($this->returnCallback(function ($testDescriptor) use ($descriptor) {
            return $testDescriptor === $descriptor ? DataInsertTaskObject::class : null;
        }));

        $insertedData = [];
        $storage = $this->getMockBuilder(Storage::class)->getMock();
        $storage->method('supports')->will($this->returnValue(true));
        $storage->method('insert')->will($this->returnCallback(function ($object) use (&$insertedData) {
            $insertedData[] = $object->getActstatid();
        }));

        $file = new SplFileInfo(__DIR__ . '/_fixtures/data.xml');
        $state = new ArrayState;
        $state->setParameter(Task::FILES_TO_INSERT_PARAM, [$file->getPathname()]);

        $serializer = $this->getMockBuilder(SerializerInterface::class)->getMock();
        $serializer->method('deserialize')->will($this->throwException(new InvalidArgumentException));

        $reader = new XmlReader;
        $reader->open($file, $descriptor);
        $task = new DataInsertTask($entityManager, new XmlParser($reader, $serializer), $storage);

        $this->expectException(TaskException::class);
        $task->run($state);
        $reader->close();
    }
}

/**
 * Мок для проверки задачи о записи данных в БД.
 */
class DataInsertTaskObject
{
    private $actstatid;
    private $name;

    public function setActstatid(int $actstatid)
    {
        $this->actstatid = $actstatid;
    }

    public function getActstatid()
    {
        return $this->actstatid;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }
}
