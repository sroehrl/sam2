# neoan3 Transformer handling 

## What it does
This app handles neoan3 transformers and automated crud-operations based on magic method handling.

_Installation_

`composer require neoan3-apps/transformer`

_Preparation_

You will need the following setup:
- [Model](#model)
- [Transformer](#transformer)

## Model
A model (model/some/Some.model.php) can look like this
```PHP
<?php

namespace Neoan3\Model;

use Neoan3\Apps\Transformer;

class SomeModel extends IndexModel
{
    static function __callStatic($method, $arguments)
    {
        $handOff = [$method, $arguments];
        return Transformer::addMagic(...$handOff);
    }
    static function test($some){
        var_dump('If function exists in model, it will be executed');
    }

}
```

_addMagic($method, $arguments, $customTransformerClass = false, $assumesUniqueIdsInDb = true, $customPathForMigrationJSON = false)_

Can be placed in the callStatic of your neoan3 model.

$customTransformerClass traces to the model's transformer by default but can also be provided (e.g. SomeTransfomer::class).

$assumesUniqueIdsInDb defaults to true and assumes the BINARY(16) database-handling neoan3-apps/db (required) uses.
If set to false, auto-incremented integers are expected.

$customPathForMigrationJSON can be used if migrate JSONs other than the one present in the model should be used for the transformer. This in not recommended.

_$transformerInstance = new Transformer($customTransformerClass, $modelName, $assumesUniqueIdsInDb = true, $customPathForMigrationJSON = false)_

Alternatively, you can create an instance of Transformer. Note that you have to provide the transformer (class) and model (string) in that case.

## Transformer

A transformer (model/some/Some.transformer.php) can look like this
```PHP
<?php

namespace Neoan3\Model;


class SomeTransformer implements IndexTransformer
{
    static function modelStructure()
    {
        return [
            'name' => [
                'required' => true,
                'on_read' => function($input,$all){ return $input . ' (human)';}
            ],
            'assignments' => [
                'translate' => 'task_assignment',
                'depth' => 'many',
                'required_fields' => ['user_id']
            ]           
        ];
    }
}

```

A transformer defines behavior for CRUD operations with the following listeners:
- on_read
- on_update
- on_creation
- on_delete

neoan3 model handling assumes every entity to have a master-table in the database and potential slave-tables associated with it.
Whether of not a relation is one-to-one or one-to-many can be indicated by "depth" (one | many).
