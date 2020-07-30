<?php

declare(strict_types=1);

/*
 * @copyright   2020 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\CustomObjectsBundle\Tests\Unit\Helper;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\LeadBundle\Segment\ContactSegmentFilter;
use MauticPlugin\CustomObjectsBundle\CustomFieldType\CustomFieldTypeInterface;
use MauticPlugin\CustomObjectsBundle\Helper\CustomFieldQueryBuilder;
use MauticPlugin\CustomObjectsBundle\Provider\ConfigProvider;
use MauticPlugin\CustomObjectsBundle\Provider\CustomFieldTypeProvider;
use MauticPlugin\CustomObjectsBundle\Repository\CustomFieldRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CustomFieldQueryBuilderTest extends TestCase
{
    /**
     * @var CustomFieldQueryBuilder
     */
    private $customFieldQueryBuilder;

    /**
     * @var ContactSegmentFilter|MockObject
     */
    private $segmentFilter;

    public function setUp(): void
    {
        parent::setUp();

        defined('MAUTIC_TABLE_PREFIX') or define('MAUTIC_TABLE_PREFIX', '');

        $this->segmentFilter = $this->createMock(ContactSegmentFilter::class);
        $this->segmentFilter
            ->method('getField')
            ->willReturn(1);
        $this->segmentFilter
            ->method('getType')
            ->willReturn('int');
    }

    public function testBuildQuery1Level(): void
    {
        $this->constructWithExpectedLimit(1);

        $unionQueryContainer = $this->customFieldQueryBuilder->buildQuery('alias', $this->segmentFilter);

        $this->assertCount(1, $unionQueryContainer);
        $this->assertSame(
            'SELECT contact_id FROM custom_field_value_int alias_value INNER JOIN custom_item_xref_contact alias_contact ON alias_value.custom_item_id = alias_contact.custom_item_id WHERE alias_value.custom_field_id = :alias_custom_field_id',
            $unionQueryContainer->getSQL()
        );
    }

    public function testBuildQuery2Level(): void
    {
        $this->constructWithExpectedLimit(2);

        $unionQueryContainer = $this->customFieldQueryBuilder->buildQuery('alias', $this->segmentFilter);

        $this->assertCount(3, $unionQueryContainer);
        $this->assertSame(
            'SELECT contact_id FROM custom_field_value_int alias_value INNER JOIN custom_item_xref_contact alias_contact ON alias_value.custom_item_id = alias_contact.custom_item_id WHERE alias_value.custom_field_id = :alias_custom_field_id UNION ALL SELECT contact_id FROM custom_field_value_int alias_value INNER JOIN custom_item_xref_custom_item alias_item_xref_1 ON alias_item_xref_1.custom_item_id_lower = alias_value.custom_item_id INNER JOIN custom_item_xref_contact alias_contact ON alias_item_xref_1.custom_item_id_higher = alias_contact.custom_item_id WHERE alias_value.custom_field_id = :alias_custom_field_id UNION ALL SELECT contact_id FROM custom_field_value_int alias_value INNER JOIN custom_item_xref_custom_item alias_item_xref_1 ON alias_item_xref_1.custom_item_id_higher = alias_value.custom_item_id INNER JOIN custom_item_xref_contact alias_contact ON alias_item_xref_1.custom_item_id_lower = alias_contact.custom_item_id WHERE alias_value.custom_field_id = :alias_custom_field_id',
            $unionQueryContainer->getSQL()
        );
    }

    public function testBuildQuery3Level(): void
    {
        $this->markTestSkipped('Not implemented yet');
        $this->constructWithExpectedLimit(2);

        $unionQueryContainer = $this->customFieldQueryBuilder->buildQuery('alias', $this->segmentFilter);

        $this->assertCount(3, $unionQueryContainer);
        $this->assertSame(
            'SELECT contact_id FROM custom_field_value_int alias_value INNER JOIN custom_item_xref_custom_item alias_item_xref_1 ON alias_item_xref_1.custom_item_id_lower = alias_value.custom_item_id INNER JOIN custom_item_xref_custom_item alias_item_xref_2 ON alias_item_xref_1.custom_item_id_lower = alias_value.custom_item_id INNER JOIN custom_item_xref_contact alias_contact ON alias_item_xref_2.custom_item_id_higher = alias_contact.custom_item_id WHERE alias_value.custom_field_id = :alias_custom_field_id UNION ALL SELECT contact_id FROM custom_field_value_int alias_value INNER JOIN custom_item_xref_custom_item alias_item_xref_1 ON alias_item_xref_1.custom_item_id_higher = alias_value.custom_item_id INNER JOIN custom_item_xref_custom_item alias_item_xref_2 ON alias_item_xref_1.custom_item_id_lower = alias_value.custom_item_id INNER JOIN custom_item_xref_contact alias_contact ON alias_item_xref_2.custom_item_id_higher = alias_contact.custom_item_id WHERE alias_value.custom_field_id = :alias_custom_field_id UNION ALL SELECT contact_id FROM custom_field_value_int alias_value INNER JOIN custom_item_xref_custom_item alias_item_xref_1 ON alias_item_xref_1.custom_item_id_lower = alias_value.custom_item_id INNER JOIN custom_item_xref_custom_item alias_item_xref_2 ON alias_item_xref_1.custom_item_id_higher = alias_value.custom_item_id INNER JOIN custom_item_xref_contact alias_contact ON alias_item_xref_2.custom_item_id_lower = alias_contact.custom_item_id WHERE alias_value.custom_field_id = :alias_custom_field_id UNION ALL SELECT contact_id FROM custom_field_value_int alias_value INNER JOIN custom_item_xref_custom_item alias_item_xref_1 ON alias_item_xref_1.custom_item_id_higher = alias_value.custom_item_id INNER JOIN custom_item_xref_custom_item alias_item_xref_2 ON alias_item_xref_1.custom_item_id_higher = alias_value.custom_item_id INNER JOIN custom_item_xref_contact alias_contact ON alias_item_xref_2.custom_item_id_lower = alias_contact.custom_item_id WHERE alias_value.custom_field_id = :alias_custom_field_id',
            $unionQueryContainer->getSQL()
        );
    }

    private function constructWithExpectedLimit(int $limit): void
    {
        $entityManager = $this->createMock(EntityManager::class);
        $entityManager
            ->method('getConnection')
            ->willReturn($this->createMock(Connection::class));

        $customField = $this->createMock(CustomFieldTypeInterface::class);
        $customField
            ->method('getTableName')
            ->willReturn('custom_field_value_int');

        $fieldTypeProvider = $this->createMock(CustomFieldTypeProvider::class);
        $fieldTypeProvider
            ->method('getType')
            ->with('int')
            ->willReturn($customField);

        $coreParametersHelper = $this->createMock(CoreParametersHelper::class);
        $coreParametersHelper->expects($this->once())
            ->method('get')
            ->with(ConfigProvider::CONFIG_PARAM_ITEM_VALUE_TO_CONTACT_RELATION_LIMIT)
            ->willReturn($limit);

        $this->customFieldQueryBuilder = new CustomFieldQueryBuilder(
            $entityManager,
            $fieldTypeProvider,
            $coreParametersHelper,
            $this->createMock(CustomFieldRepository::class)
        );
    }
}
