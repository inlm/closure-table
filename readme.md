
# Closure Table

Traits for Closure Table for LeanMapper.


## Installation

[Download a latest package](https://github.com/inlm/closure-table/releases) or use [Composer](http://getcomposer.org/):

```
composer require inlm/closure-table
```

Library requires PHP 5.4.0 or later.


## Example

### Database tables

``` sql
CREATE TABLE `category` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_czech_ci NOT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `category_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `category` (`id`)
) ENGINE=InnoDB;


CREATE TABLE `category_closure` (
  `ancestor_id` int(10) unsigned NOT NULL,
  `descendant_id` int(10) unsigned NOT NULL,
  `depth` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`ancestor_id`,`descendant_id`),
  KEY `descendant_id` (`descendant_id`),
  CONSTRAINT `category_closure_ibfk_1` FOREIGN KEY (`ancestor_id`) REFERENCES `category` (`id`),
  CONSTRAINT `category_closure_ibfk_2` FOREIGN KEY (`descendant_id`) REFERENCES `category` (`id`)
) ENGINE=InnoDB;
```


### Repository

``` php
<?php
class CategoryRepository extends \LeanMapper\Repository
{
	use \Inlm\ClosureTable\TClosureTableRepository;
}
```


### Entity

``` php
<?php
/**
 * @property-read int $id
 * @property string $name
 * @property Category|NULL $parent m:hasOne(parent_id)
 * @property-read Category[] $parents m:hasMany(descendant_id:category_closure:ancestor_id:category)  [optional]
 */
class Category extends \LeanMapper\Entity
{
	use \Inlm\ClosureTable\TClosureTableEntity;


	/**
	 * Returns direct children, ordered by 'name'
	 * @return Category[]
	 */
	public function getChildren()
	{
		return $this->getChildrenEntities(array('name'));
	}
}
```

Entity API:

``` php
$category->getChildren(); // returns direct children (children.parent_id = category.id)
$category->getParents(); // returns parent entities ordered by `depth` (from root)
$category->getDescendants(); // returns all descendants
$category->getAncestors(); // returns all ancestors
$category->getDepth(); // returns entity depth in collection, for standalone entity returns 0
```

------------------------------

License: [New BSD License](license.md)
<br>Author: Jan Pecha, https://www.janpecha.cz/
