<?php

namespace A2lix\I18nDoctrineBundle\Doctrine\ORM\EventListener;

use A2lix\I18nDoctrineBundle\EventListener\DoctrineListener as BaseDoctrineListener,
    Doctrine\ORM\Event\LoadClassMetadataEventArgs,
    Doctrine\ORM\Mapping\ClassMetadata,
    Doctrine\ORM\Events;

class DoctrineListener extends BaseDoctrineListener
{
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs)
    {
        $classMetadata = $eventArgs->getClassMetadata();

        if (null === $classMetadata->reflClass) {
            return;
        }

        // Translatable object?
        if ($this->isTranslatable($classMetadata->reflClass) && !$classMetadata->hasAssociation('translations')) {
            $classMetadata->mapOneToMany(array(
                'fieldName' => 'translations',
                'mappedBy' => 'translatable',
                'indexBy' => 'locale',
                'cascade' => array('persist', 'merge', 'remove'),
                'targetEntity' => $classMetadata->name . 'Translation'
            ));
        }

        // Translation object?
        if ($classMetadata->reflClass->hasProperty('translatable') && !$classMetadata->hasAssociation('translatable')) {
            $classMetadata->mapManyToOne(array(
                'fieldName' => 'translatable',
                'inversedBy' => 'translations',
                'joinColumns' => array(array(
                    'name' => 'translatable_id',
                    'referencedColumnName' => 'id',
                    'onDelete' => 'CASCADE'
                )),
                'targetEntity' => substr($classMetadata->name, 0, -11)
            ));

            // Unique constraint
            $uniqueConstraints = isset($classMetadata->table['uniqueConstraints']) ? $classMetadata->table['uniqueConstraints'] : array();
            $name = $classMetadata->getTableName() . '_unique_translation';
            if (!$this->hasUniqueTranslationConstraint($classMetadata, $name)) {
                $uniqueConstraints[$name] = array(
                    'name' => $name,
                    'columns' => array('translatable_id', 'locale')
                );
            }
            $classMetadata->setPrimaryTable(array(
                'uniqueConstraints' => $uniqueConstraints,
            ));
        }
    }

    protected function hasUniqueTranslationConstraint(ClassMetadata $classMetadata, $name)
    {
        if (!isset($classMetadata->table['uniqueConstraints'])) {
            return false;
        }

        return isset($classMetadata->table['uniqueConstraints'][$name]);
    }

    public function getSubscribedEvents()
    {
        return array(
            Events::loadClassMetadata,
        );
    }

}