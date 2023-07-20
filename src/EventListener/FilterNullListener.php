<?php
namespace TorqIT\GridNullFilterBundle\EventListener;
  
use Pimcore\Model\DataObject\Product\Listing as ProductListing;
use Symfony\Component\EventDispatcher\GenericEvent;

class FilterNullListener {
     
    public function beforeListLoad (GenericEvent $e): void
    {
        $context = $e->getArgument('context');
        if (!isset($context['filter'])) {
            return;
        }

        $filterJson = $context['filter'];
        $filters = json_decode($filterJson);

        if (!is_array($filters )) {
            return;
        }

        $nullFilterConditionsArray = [];
        foreach ($filters as $filter) {
            if ($filter->type == 'isNullOrEmpty') {
                $nullFilterConditionsArray[] = "( {$filter->property} = ''  OR {$filter->property} is NULL )";
            }
        }
        $nullFilterConditions = implode(' AND ', $nullFilterConditionsArray);

        /** @var ProductListing $list */
        $list = $e->getArgument('list');
        $existingCondition = $list->getCondition();

        $newConditionsArray = [];
        if (strlen($existingCondition) > 0) $newConditionsArray[] = $existingCondition;
        if (strlen($nullFilterConditions) > 0) $newConditionsArray[] = $nullFilterConditions;
        $newConditions = implode(' AND ', $newConditionsArray, );
        $list->setCondition($newConditions);

    }
}
