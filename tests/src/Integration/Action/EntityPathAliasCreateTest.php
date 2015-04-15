<?php

/**
 * @file
 * Contains \Drupal\Tests\rules\Integration\Action\EntityPathAliasCreateTest.
 */

namespace Drupal\Tests\rules\Integration\Action;

use Drupal\Core\Entity\ContentEntityType;
use Drupal\Tests\rules\Integration\RulesEntityIntegrationTestBase;

/**
 * @coversDefaultClass \Drupal\rules\Plugin\Action\EntityPathAliasCreate
 * @group rules_actions
 */
class EntityPathAliasCreateTest extends RulesEntityIntegrationTestBase {

  /**
   * A constant that will be used instead of an entity.
   */
  const ENTITY_REPLACEMENT = 'This is a fake entity';

  /**
   * The action to be tested.
   *
   * @var \Drupal\rules\Core\RulesActionInterface
   */
  protected $action;

  /**
   * The mocked alias storage service.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject|\Drupal\Core\Path\AliasStorageInterface
   */
  protected $aliasStorage;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Prepare mocked bundle field definition. This is needed because
    // EntityCreateDeriver adds required contexts for required fields, and
    // assumes that the bundle field is required.
    $this->bundleFieldDefinition = $this->getMockBuilder('Drupal\Core\Field\BaseFieldDefinition')
      ->disableOriginalConstructor()
      ->getMock();

    // The next methods are mocked because EntityCreateDeriver executes them,
    // and the mocked field definition is instantiated without the necessary
    // information.
    $this->bundleFieldDefinition
      ->expects($this->once())
      ->method('getCardinality')
      ->willReturn(1);

    $this->bundleFieldDefinition
      ->expects($this->once())
      ->method('getType')
      ->willReturn('string');

    $this->bundleFieldDefinition
      ->expects($this->once())
      ->method('getLabel')
      ->willReturn('Bundle');

    $this->bundleFieldDefinition
      ->expects($this->once())
      ->method('getDescription')
      ->willReturn('Bundle mock description');

    // Prepare an content entity type instance.
    $this->entityType = new ContentEntityType([
      'id' => 'test',
      'label' => 'Test',
      'entity_keys' => [
        'bundle' => 'bundle',
      ],
    ]);

    // Prepare mocked entity storage.
    $this->entityTypeStorage = $this->getMockBuilder('Drupal\Core\Entity\EntityStorageBase')
      ->setMethods(['create'])
      ->setConstructorArgs([$this->entityType])
      ->getMockForAbstractClass();

    $this->entityTypeStorage
      ->expects($this->any())
      ->method('create')
      ->willReturn(self::ENTITY_REPLACEMENT);

    // Prepare mocked entity manager.
    $this->entityManager = $this->getMockBuilder('Drupal\Core\Entity\EntityManager')
      ->setMethods(['getBundleInfo', 'getStorage', 'getDefinitions', 'getBaseFieldDefinitions'])
      ->setConstructorArgs([
        $this->namespaces,
        $this->moduleHandler,
        $this->cacheBackend,
        $this->languageManager,
        $this->getStringTranslationStub(),
        $this->getClassResolverStub(),
        $this->typedDataManager,
        $this->getMock('Drupal\Core\KeyValueStore\KeyValueStoreInterface'),
        $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface')
      ])
      ->getMock();

    // Return the mocked storage controller.
    $this->entityManager
      ->expects($this->any())
      ->method('getStorage')
      ->willReturn($this->entityTypeStorage);

    // Return a mocked list of base fields defintions.
    $this->entityManager
      ->expects($this->any())
      ->method('getBaseFieldDefinitions')
      ->willReturn(['bundle' => $this->bundleFieldDefinition]);

    // Return a mocked list of entity types.
    $this->entityManager
      ->expects($this->any())
      ->method('getDefinitions')
      ->willReturn(['test' => $this->entityType]);

    // Return some dummy bundle information for now, so that the entity manager
    // does not call out to the config entity system to get bundle information.
    $this->entityManager
      ->expects($this->any())
      ->method('getBundleInfo')
      ->with($this->anything())
      ->willReturn(['test' => ['label' => 'Test']]);

    $this->container->set('entity.manager', $this->entityManager);

    $this->aliasStorage = $this->getMock('Drupal\Core\Path\AliasStorageInterface');
    $this->container->set('path.alias_storage', $this->aliasStorage);

    // Instantiate the action we are testing.
    $this->action = $this->actionManager->createInstance('rules_entity_path_alias_create:entity:test');
  }

  /**
   * Tests the summary.
   *
   * @covers ::summary
   */
  public function testSummary() {
    $this->assertEquals('Create test path alias', $this->action->summary());
  }

  /**
   * Tests the action execution with an unsaved node.
   *
   * @covers ::execute
   */
  public function testActionExecutionWithUnsavedEntity() {
    $entity = $this->getMockEntity();
    $entity->expects($this->once())
      ->method('isNew')
      ->will($this->returnValue(TRUE));

    // Test that new entities are saved first.
    $entity->expects($this->once())
      ->method('save');

    $this->action->setContextValue('entity', $entity)
      ->setContextValue('alias', 'about');

    $this->action->execute();
  }

  /**
   * Tests the action execution with a saved entity.
   *
   * @covers ::execute
   */
  public function testActionExecutionWithSavedEntity() {
    $node = $this->getMockEntity();
    $node->expects($this->once())
      ->method('isNew')
      ->will($this->returnValue(FALSE));

    // Test that existing entities are not saved again.
    $node->expects($this->never())
      ->method('save');

    $this->action->setContextValue('entity', $node)
      ->setContextValue('alias', 'about');

    $this->action->execute();
  }

  /**
   * Creates a mock entity.
   *
   * @return \PHPUnit_Framework_MockObject_MockObject|\Drupal\Core\Entity\EntityInterface
   *   The mocked entity object.
   */
  protected function getMockEntity() {
    $language = $this->getMock('Drupal\Core\language\LanguageInterface');
    $language->expects($this->once())
      ->method('getId')
      ->will($this->returnValue('en'));

    $entity = $this->getMock('Drupal\Core\Entity\EntityInterface');
    $entity->expects($this->once())
      ->method('language')
      ->will($this->returnValue($language));

    $url = $this->getMockBuilder('Drupal\Core\Url')
      ->disableOriginalConstructor()
      ->getMock();

    $url->expects($this->once())
      ->method('getInternalPath')
      ->will($this->returnValue('test/1'));

    $entity->expects($this->once())
      ->method('urlInfo')
      ->with('canonical')
      ->will($this->returnValue($url));

    $this->aliasStorage->expects($this->once())
      ->method('save')
      ->with('test/1', 'about', 'en');

    return $entity;
  }

}
