<?php

namespace TorqIT\GridNullFilterBundle\EventListener;

use Pimcore\Model\DataObject\ClassDefinition\Data\ImageGallery;
use Pimcore\Model\DataObject\ClassDefinition\Data\ManyToOneRelation;
use Pimcore\Model\DataObject\Product\Listing as ProductListing;
use Symfony\Component\EventDispatcher\GenericEvent;

class FilterNullListener
{
    public function beforeListLoad(GenericEvent $e): void
    {
        $context = $e->getArgument('context');
        if (!isset($context['filter'])) {
            return;
        }

        $filterJson = $context['filter'];
        $filters = json_decode($filterJson);

        if (!is_array($filters)) {
            return;
        }

        $class = $context["class"];
        $className = "Pimcore\\Model\\DataObject\\" . $class;
        $classInstance = new $className();

        $fieldDefintions = $classInstance->getClass()->getFieldDefinitions();

        /** @var ProductListing $list */
        $list = $e->getArgument('list');
        $existingCondition = $list->getCondition();
        $nullFilterConditionsArray = [];

        foreach ($filters as &$filter) {
            if ($filter->type === 'isNullOrEmpty') {
                if (!array_key_exists($filter->property, $fieldDefintions)) {
                    continue;
                }

                $fieldDef = $fieldDefintions[$filter->property];

                if (!empty($existingCondition)) {
                    $prop = preg_quote($filter->property, '/');

                    $existingCondition = preg_replace(
                        '/(?:AND\s+)?\(\(?(?:\s*`?' . $prop . '`?\s*(?:IS NULL|=\s*\'\')\s*OR\s*`?' . $prop . '`?\s*(?:=\s*\'\'|IS NULL)\s*)\)\)?/i',
                        '',
                        $existingCondition
                    );
                }

                if ($fieldDef instanceof ImageGallery) {
                    $imagesProperty = $filter->property . "__images";
                    $nullFilterConditionsArray[] = "( {$imagesProperty} = ''  OR {$imagesProperty} IS NULL )";
                } else if ($fieldDef instanceof ManyToOneRelation) {
                    $manyToOneRelationProperty = $filter->property . "__id";
                    $nullFilterConditionsArray[] = "( {$manyToOneRelationProperty} = ''  OR {$manyToOneRelationProperty} IS NULL )";
                } else {
                    $nullFilterConditionsArray[] = "( {$filter->property} = ''  OR {$filter->property} IS NULL )";
                }
            }
        }

        $nullFilterConditions = implode(' AND ', $nullFilterConditionsArray);

        $newConditionsArray = [];
        if (strlen($existingCondition) > 0) $newConditionsArray[] = $existingCondition;
        if (strlen($nullFilterConditions) > 0) $newConditionsArray[] = $nullFilterConditions;
        $newConditions = implode(' AND ', $newConditionsArray);
        $list->setCondition($newConditions);
    }
}
