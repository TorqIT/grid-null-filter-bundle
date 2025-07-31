<?php

namespace TorqIT\GridNullFilterBundle\EventListener;

use Pimcore\Model\DataObject\ClassDefinition\Data\ClassificationStore;
use Pimcore\Model\DataObject\ClassDefinition\Data\ImageGallery;
use Pimcore\Model\DataObject\ClassDefinition\Data\ManyToOneRelation;
use Pimcore\Model\DataObject\Listing;
use Pimcore\Model\DataObject\Product\Listing as ProductListing;
use stdClass;
use Symfony\Component\EventDispatcher\GenericEvent;

class FilterNullListener
{
    /**
     * Main method that handles the filter application before a list is loaded
     */
    public function beforeListLoad(GenericEvent $e): void
    {
        $context = $e->getArgument('context');
        if (!$this->isValidFilterContext($context)) {
            return;
        }

        $filters = json_decode($context['filter']);
        if (!is_array($filters)) {
            return;
        }

        $class = $context["class"];
        $classInstance = $this->createClassInstance($class);
        $classDef = $classInstance->getClass();
        if ($classDef === null) {
            return;
        }
        $fieldDefinitions = $classDef->getFieldDefinitions();

        /** @var ProductListing $list */
        $list = $e->getArgument('list');
        $existingCondition = $list->getCondition();
        $nullFilterConditionsArray = $this->processFilters($filters, $fieldDefinitions, $existingCondition, $context);

        $this->applyConditionsToList($list, $existingCondition, $nullFilterConditionsArray);
    }

    /**
     * Validates if the context contains filter information
     */
    private function isValidFilterContext(array $context): bool
    {
        return isset($context['filter']);
    }

    private function createClassInstance(string $class): object
    {
        $className = "Pimcore\\Model\\DataObject\\" . $class;

        if (!class_exists($className)) {
            throw new \InvalidArgumentException("Class {$className} does not exist.");
        }

        return new $className();
    }

    /**
     * Process all filters and build condition array
     */
    private function processFilters(array $filters, array $fieldDefinitions, string &$existingCondition, array $context): array
    {
        $nullFilterConditionsArray = [];

        foreach ($filters as $filter) {
            if ($filter->type === 'isNullOrEmpty') {
                $this->processNullFilter($filter, $fieldDefinitions, $existingCondition, $nullFilterConditionsArray, $context);
            }
        }

        return $nullFilterConditionsArray;
    }

    /**
     * Process a single null filter
     */
    private function processNullFilter(
        stdClass $filter,
        array $fieldDefinitions,
        string &$existingCondition,
        array &$nullFilterConditionsArray,
        array $context
    ): void {
        // Initialize the matches variable before passing it by reference
        $matches = [];

        // Check if this is a classification store field
        if ($this->isClassificationStoreField($filter->property, $matches)) {
            $this->handleClassificationStoreFilter(
                $matches,
                $filter->property,
                $fieldDefinitions,
                $existingCondition,
                $nullFilterConditionsArray,
                $context
            );
        } else {
            $this->handleStandardFilter(
                $filter->property,
                $fieldDefinitions,
                $existingCondition,
                $nullFilterConditionsArray
            );
        }
    }

    /**
     * Check if a property is a classification store field
     */
    private function isClassificationStoreField(string $property, array &$matches): bool
    {
        return preg_match('/^~classificationstore~([^~]+)~(\d+)-(\d+)$/', $property, $matches) === 1;
    }

    /**
     * Handle a classification store field filter
     */
    private function handleClassificationStoreFilter(
        array $matches,
        string $property,
        array $fieldDefinitions,
        string &$existingCondition,
        array &$nullFilterConditionsArray,
        array $context
    ): void {
        $fieldname = $matches[1];
        $groupId = $matches[2];
        $keyId = $matches[3];

        if (!array_key_exists($fieldname, $fieldDefinitions)) {
            return;
        }

        $fieldDef = $fieldDefinitions[$fieldname];
        if (!($fieldDef instanceof ClassificationStore)) {
            return;
        }

        if (!isset($context["classId"])) {
            throw new \InvalidArgumentException("Missing classId in context for classification store filter");
        }

        $this->removeExistingCondition($property, $existingCondition);
        $nullFilterConditionsArray[] = $this->buildClassificationStoreCondition($fieldname, $groupId, $keyId, $context["classId"]);
    }

    private function buildClassificationStoreCondition(
        string $fieldname,
        string $groupId,
        string $keyId,
        string $classId
    ): string {
        $alias = "cskey_{$fieldname}_{$groupId}_{$keyId}";

        return "NOT EXISTS (
            SELECT 1 FROM object_classificationstore_data_" . $classId . " AS {$alias}
            WHERE {$alias}.id = oo_id
            AND {$alias}.fieldname = '" . $fieldname . "'
            AND {$alias}.groupId = " . $groupId . "
            AND {$alias}.keyId = " . $keyId . "
            AND {$alias}.language = 'default'
            AND {$alias}.value IS NOT NULL
            AND {$alias}.value != ''
        )";
    }

    /**
     * Handle a standard (non-classification store) field filter
     */
    private function handleStandardFilter(
        string $property,
        array $fieldDefinitions,
        string &$existingCondition,
        array &$nullFilterConditionsArray
    ): void {
        if (!array_key_exists($property, $fieldDefinitions)) {
            return;
        }

        $fieldDef = $fieldDefinitions[$property];
        $this->removeExistingCondition($property, $existingCondition);
        $nullFilterConditionsArray[] = $this->buildStandardFieldCondition($property, $fieldDef);
    }

    /**
     * Build the SQL condition for a standard field
     */
    private function buildStandardFieldCondition(string $property, object $fieldDef): string
    {
        if ($fieldDef instanceof ImageGallery) {
            $imagesProperty = $property . "__images";
            return "( {$imagesProperty} = ''  OR {$imagesProperty} IS NULL )";
        } else if ($fieldDef instanceof ManyToOneRelation) {
            $manyToOneRelationProperty = $property . "__id";
            return "( {$manyToOneRelationProperty} = ''  OR {$manyToOneRelationProperty} IS NULL )";
        } else {
            return "( {$property} = ''  OR {$property} IS NULL )";
        }
    }

    /**
     * Remove existing condition for a property if it exists
     */
    private function removeExistingCondition(string $property, string &$existingCondition): void
    {
        if (!empty($existingCondition)) {
            $prop = preg_quote($property, '/');
            $existingCondition = preg_replace(
                '/(?:AND\s+)?\(\(?(?:\s*`?' . $prop . '`?\s*(?:IS NULL|=\s*\'\')\s*OR\s*`?' . $prop . '`?\s*(?:=\s*\'\'|IS NULL)\s*)\)\)?/i',
                '',
                $existingCondition
            );
        }
    }

    /**
     * Apply all conditions to the listing
     */
    private function applyConditionsToList(Listing $list, string $existingCondition, array $nullFilterConditionsArray): void
    {
        $nullFilterConditions = implode(' AND ', $nullFilterConditionsArray);

        $newConditionsArray = [];
        if (strlen($existingCondition) > 0) $newConditionsArray[] = $existingCondition;
        if (strlen($nullFilterConditions) > 0) $newConditionsArray[] = $nullFilterConditions;

        $newConditions = implode(' AND ', $newConditionsArray);
        $list->setCondition($newConditions);
    }
}
