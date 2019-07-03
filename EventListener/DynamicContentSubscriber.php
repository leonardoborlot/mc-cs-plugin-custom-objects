<?php

declare(strict_types=1);

/*
 * @copyright   2019 Mautic Contributors. All rights reserved
 * @author      Mautic Inc.
 *
 * @link        https://mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\CustomObjectsBundle\EventListener;

use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\DynamicContentBundle\DynamicContentEvents;
use Mautic\DynamicContentBundle\Event\ContactFiltersEvaluateEvent;
use Mautic\EmailBundle\EventListener\MatchFilterForLeadTrait;
use MauticPlugin\CustomObjectsBundle\Exception\InvalidArgumentException;
use MauticPlugin\CustomObjectsBundle\Helper\QueryFilterHelper;
use MauticPlugin\CustomObjectsBundle\Provider\ConfigProvider;
use MauticPlugin\CustomObjectsBundle\Repository\DbalQueryTrait;
use PDOException;
use MauticPlugin\CustomObjectsBundle\Segment\Query\Filter\FilterQueryFactory;
use MauticPlugin\CustomObjectsBundle\Exception\InvalidSegmentFilterException;

class DynamicContentSubscriber extends CommonSubscriber
{
    use MatchFilterForLeadTrait;
    use DbalQueryTrait;

    /**
     * @var FilterQueryFactory
     */
    private $filterQueryFactory;

    /**
     * @var QueryFilterHelper
     */
    private $queryFilterHelper;

    /**
     * @var ConfigProvider
     */
    private $configProvider;

    /**
     * @param FilterQueryFactory $filterQueryFactory
     * @param QueryFilterHelper  $queryFilterHelper
     * @param ConfigProvider     $configProvider
     */
    public function __construct(
        FilterQueryFactory $filterQueryFactory,
        QueryFilterHelper $queryFilterHelper,
        ConfigProvider $configProvider)
    {
        $this->filterQueryFactory = $filterQueryFactory;
        $this->queryFilterHelper  = $queryFilterHelper;
        $this->configProvider     = $configProvider;
    }

    /**
     * @return mixed[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            DynamicContentEvents::ON_CONTACTS_FILTER_EVALUATE => ['evaluateFilters', 0],
        ];
    }

    /**
     * @param ContactFiltersEvaluateEvent $event
     *
     * @throws InvalidArgumentException
     */
    public function evaluateFilters(ContactFiltersEvaluateEvent $event): void
    {
        if (!$this->configProvider->pluginIsEnabled()) {
            return;
        }

        $eventFilters = $event->getFilters();

        if ($event->isEvaluated()) {
            return;
        }

        foreach ($eventFilters as $key => $eventFilter) {
            $queryAlias = "filter_{$key}";

            try {
                $filterQueryBuilder = $this->filterQueryFactory->configureQueryBuilderFromSegmentFilter($eventFilter, $queryAlias);
            } catch (InvalidSegmentFilterException $e) {
                continue;
            }

            $this->queryFilterHelper->addContactIdRestriction($filterQueryBuilder, $queryAlias, (int) $event->getContact()->getId());

            try {
                if ($this->executeSelect($filterQueryBuilder)->rowCount()) {
                    $event->setIsEvaluated(true);
                    $event->setIsMatched(true);
                } else {
                    $event->setIsEvaluated(true);
                }
            } catch (PDOException $e) {
                $this->logger->addError('Failed to evaluate dynamic content for custom object '.$e->getMessage());

                throw $e;
            }

            $event->stopPropagation();  // The filter is ours, we won't allow no more processing
        }
    }
}
