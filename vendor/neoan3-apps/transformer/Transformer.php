<?php

namespace Neoan3\Apps;

use Exception;
use Neoan3\Model\IndexModel;

/**
 * Class Transformer
 * @package Neoan3\Apps
 */
class Transformer
{
    /**
     * @var array
     */
    private static $knownModels = [];
    /**
     * @var \MockTransformer $transformer
     */
    private static $transformer;
    private static $model = '';
    private static $migratePath;
    private static $assumesUuid;

    /**
     * Transformer constructor.
     *
     * @param      $transformer
     * @param      $model
     * @param bool $migratePath
     * @param bool $assumesUuid
     */
    function __construct($transformer, $model, $assumesUuid = true, $migratePath = false)
    {
        self::assignVariables($transformer, $model, $assumesUuid, $migratePath);
    }

    /**
     * @param string      $method
     * @param array       $arguments
     * @param             $transformer
     * @param bool        $assumesUuid
     * @param bool|string $migratePath
     *
     * @return mixed
     */
    static function addMagic($method, $arguments, $transformer = false, $assumesUuid = true, $migratePath = false)
    {
        $from = debug_backtrace();
        if (!method_exists($from[1]['class'], $method)) {
            $parts = explode('\\', $from[1]['class']);
            $model = lcfirst(substr(end($parts), 0, strlen('Model') * -1));
            if (!$transformer) {
                $include = '\\Neoan3\\Model\\' . ucfirst($model) . 'Transformer';
                $transformer = $include;
            }
            self::assignVariables($transformer, $model, $assumesUuid, $migratePath);
            return self::$method(...$arguments);
        } else {
            return $from[1]['class']::$method(...$arguments);
        }
    }

    /**
     * @param $transformer
     * @param $model
     * @param $assumesUuid
     * @param $migratePath
     */
    private static function assignVariables($transformer, $model, $assumesUuid, $migratePath)
    {
        self::$transformer = $transformer;
        self::$model = $model;
        self::$migratePath = $migratePath;
        self::$assumesUuid = $assumesUuid;
    }

    /**
     * @param $name
     * @param $arguments
     *
     * @return mixed
     * @throws Exception
     */
    static function __callStatic($name, $arguments)
    {
        $givenId = isset($arguments[1]) ? $arguments[1] : false;
        if (method_exists(self::class, $name)) {
            return call_user_func_array([self::class, $name], $arguments);
        } else {
            $parts = preg_split('/([A-Z])/', $name, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
            // does base-method exist?
            if (!method_exists(self::class, $parts[0])) {
                throw new Exception('Magic method must start with either "get", "create", "update" or "delete"');
            }
            $function = $parts[0];
            $subModel = '';
            for ($i = 1; $i < count($parts); $i++) {
                $subModel .= $parts[$i];
            }
            $subModel = lcfirst($subModel);
            $arguments[0] = [$subModel => $arguments[0]];

            return self::$function($arguments[0], $givenId, $subModel);
        }
    }

    /**
     * @param $model
     *
     * @return array
     * @throws Exception
     */
    private static function getStructure($model)
    {
        if (isset(self::$knownModels[$model])) {
            return self::$knownModels;
        }
        return IndexModel::getMigrateStructure($model, self::$migratePath);
    }


    /**
     * @param      $id
     * @param bool $includeDeleted
     *
     * @return array|mixed
     * @throws DbException
     * @throws Exception
     */
    static function get($id, $includeDeleted = false)
    {
        $structure = self::getStructure(self::$model);

        $transformer = self::$transformer::modelStructure();
        // transformer-translations?
        $translatedTransformer = [];
        foreach ($transformer as $tableOrColumn => $values) {
            if (isset($values['translate'])) {
                $translatedTransformer[$values['translate']] = $values;
                $translatedTransformer[$values['translate']]['translate'] = $tableOrColumn;
            } else {
                $translatedTransformer[$tableOrColumn] = $values;
            }
        }
        $queries = [self::$model => []];
        $model = self::$model;
        foreach ($structure as $tableOrColumn => $definition) {
            reset($definition);
            if (is_array($definition[key($definition)])) {
                // is table
                if (isset($translatedTransformer[$tableOrColumn]['protection']) &&
                    $translatedTransformer[$tableOrColumn]['protection'] == 'hidden') {
                    continue;
                }
                $queries[$tableOrColumn]['as'] =
                    isset($translatedTransformer[$tableOrColumn]['translate']) ? $translatedTransformer[$tableOrColumn]['translate'] : $tableOrColumn;;
                $queries[$tableOrColumn]['where'] = [];
                if (!$includeDeleted) {
                    $queries[$tableOrColumn]['where']['delete_date'] = '';
                }
                $queries[$tableOrColumn]['depth'] =
                    isset($translatedTransformer[$tableOrColumn]['depth']) ? $translatedTransformer[$tableOrColumn]['depth'] : 'many';
                foreach ($definition as $key => $value) {
                    if ($key == $model . '_id') {
                        $queries[$tableOrColumn]['where'][$key] = (self::$assumesUuid ? '$' : '') . $id;
                    }
                    // default
                    $queries[$tableOrColumn]['select'][$key] = $tableOrColumn . '.' . $key;
                    if (isset($translatedTransformer[$tableOrColumn])) {
                        // onRead?
                        if (isset($translatedTransformer[$tableOrColumn]['on_read']) &&
                            isset($translatedTransformer[$tableOrColumn]['on_read'][$key])) {
                            $queries[$tableOrColumn]['select'][$key] =
                                $translatedTransformer[$tableOrColumn]['on_read'][$key]($key);
                        }
                    }
                }
            } else {
                // is key of main
                $queries[$model]['depth'] = 'main';
                $queries[$model]['as'] = $model;
                if ($tableOrColumn == 'id') {
                    $queries[$model]['where'][$tableOrColumn] = (self::$assumesUuid ? '$' : '') . $id;
                    if (!$includeDeleted) {
                        $queries[$model]['where']['delete_date'] = '';
                    }
                }
                // default
                $queries[$model]['select'][$tableOrColumn] = $model . '.' . $tableOrColumn;
                if (isset($translatedTransformer[$tableOrColumn])) {
                    // is hidden/protected?
                    if (isset($translatedTransformer[$tableOrColumn]['protection'])) {
                        // do nothing for now
                        unset($queries[$model]['select'][$tableOrColumn]);
                    } else {
                        // onRead?
                        if (isset($translatedTransformer[$tableOrColumn]['on_read'])) {
                            $queries[$model]['select'][$tableOrColumn] =
                                $translatedTransformer[$tableOrColumn]['on_read']($tableOrColumn);
                        }
                        // translate?
                        if (isset($translatedTransformer[$tableOrColumn]['translate'])) {
                            $queries[$model]['select'][$tableOrColumn] .= ':' .
                                $translatedTransformer[$tableOrColumn]['translate'];
                        }
                    }
                }
            }
        }
        foreach ($queries as $i => $query) {
            $queries[$i]['select'] = implode(' ', array_values($queries[$i]['select']));
        }
        $entity = [];

        foreach ($queries as $table => $query) {
            switch ($query['depth']) {
                case 'main':
                    $exec = Db::easy($query['select'], $query['where']);
                    $entity = IndexModel::first($exec);
                    if (empty($entity)) {
                        break 2;
                    }
                    break;
                case 'one':
                    $entity[$query['as']] = IndexModel::first(Db::easy($query['select'], $query['where']));
                    break;
                case 'many':
                    $entity[$query['as']] = Db::easy($query['select'], $query['where']);
                    break;
            }
        }

        return $entity;
    }

    /**
     * @param      $obj
     * @param bool $subModel
     * @param bool $givenId
     *
     * @return array
     * @throws DbException
     * @throws Exception
     */
    static function create($obj, $givenId = false, $subModel = false)
    {
        $toDb = self::prepareForTransaction($obj, $givenId, $subModel);
        self::executeTransactions($toDb);
        if (isset($toDb[self::$model]['id']) || $givenId) {
            $id = $givenId ? $givenId : $toDb[self::$model]['id'];
            return self::get(self::sanitizeId($id));
        }
        return $toDb;
    }

    /**
     * @param      $obj
     * @param      $givenId
     * @param bool $subModel
     *
     * @return mixed
     * @throws DbException
     * @throws Exception
     */
    static function update($obj, $givenId = false, $subModel = false)
    {
        $id = self::identifyUsableId($obj, $givenId, $subModel);
        $q = ['id' => $id];
        if($subModel){
            $q = [$subModel => $q];
        }
        $existingEntity = IndexModel::first(self::find($q, false, $subModel));
        if (empty($existingEntity)) {
            throw new Exception('Cannot find entity to update');
        }
        if (!$subModel) {
            $stripped = TransformValidator::stripUnchangedData($existingEntity, $subModel ? $obj[$subModel] : $obj);
        } else {
            $stripped = $obj;
        }
        $prepared = self::prepareForTransaction($stripped, $id, $subModel, 'update');
        self::executeTransactions($prepared, ['id' => (self::$assumesUuid ? '$' : '') . $id]);
        return self::applyChangesToExistingModel($existingEntity, $obj, $subModel);
    }

    /**
     * @param      $obj
     * @param bool $void
     * @param bool $subModel
     *
     * @return array
     * @throws DbException
     * @throws Exception
     */
    static function find($obj, $void = false, $subModel = false)
    {
        if ($void) {
            throw new Exception('Malformed magic handling?');
        }
        $transformer = self::$transformer::modelStructure();
        $structure = self::getStructure(self::$model);

        $table = self::$model;
        $qualifier = 'id';
        $condition = [];
        $results = [];
        if ($subModel) {
            $qualifier = self::$model . '_id';
            $transformer = $transformer[$subModel];
            $table = isset($transformer['translate']) ? $transformer['translate'] : $subModel;
            $obj = $obj[$subModel];
            foreach ($structure[$table] as $column =>$type){
                if(isset($obj[$column])){
                    $prefix = (substr(strtolower($column), -2) == 'id' && self::$assumesUuid) ? '$' : '';
                    $condition[$column] = $prefix . $obj[$column];
                }
            }
        } else {
            foreach ($transformer as $columnOrTable => $values) {
                if (isset($obj[$columnOrTable])) {
                    $prefix = (substr(strtolower($columnOrTable), -2) == 'id' && self::$assumesUuid) ? '$' : '';
                    if (isset($values['translate'])) {
                        $condition[$values['translate']] = $prefix . $obj[$columnOrTable];
                    } else {
                        $condition[$columnOrTable] = $prefix . $obj[$columnOrTable];
                    }

                }
            }
        }

        $ids = Db::easy($table . '.' . $qualifier, $condition);
        foreach ($ids as $id) {
            $results[] = self::get($id[$qualifier]);
        }
        return $results;
    }

    /**
     * @param      $obj
     * @param      $givenId
     * @param bool $subModel
     *
     * @return array|mixed
     * @throws Exception
     */
    static function delete($obj, $givenId = false, $subModel = false)
    {
        $id = self::identifyUsableId($obj, $givenId, $subModel);

        $prefix = self::$assumesUuid ? '$' : '';
        $preparedTransactions = self::prepareForTransaction($obj, $id, $subModel, 'delete');
        foreach ($preparedTransactions as $table => $values) {
            reset($values);
            if (key($values) === 0) {
                foreach ($values as $valueSet) {
                    Db::ask($table, ['delete_date' => '.'], ['id' => $prefix . $valueSet['id']]);
                }
            } else {
                Db::ask($table, ['delete_date' => '.'], ['id' => $prefix . $values['id']]);
            }
        }
        $q = ['id' => $id];
        if($subModel){
            $q = [$subModel => $q];
        }
        return IndexModel::first(self::find($q, false, $subModel));
    }

    /**
     * @param $passIn
     * @param $givenId
     *
     * @return mixed
     * @throws Exception
     */
    private static function identifyUsableId($passIn, $givenId, $subModel)
    {
        if (!$subModel) {
            if (!isset($passIn['id']) && !$givenId) {
                throw new Exception('Cannot identify entity. No Id given.');
            }
            return $givenId ? $givenId : $passIn['id'];
        } else {
            if (!isset($passIn[$subModel]['id']) && !$givenId) {
                throw new Exception('Cannot identify entity. No Id given.');
            }
            return $givenId ? $givenId : $passIn[$subModel]['id'];
        }

    }

    /**
     * @param             $existingModel
     * @param             $approvedChanges
     * @param string|bool $subModel
     *
     * @return mixed
     */
    private static function applyChangesToExistingModel($existingModel, $approvedChanges, $subModel = false)
    {
        if (!$subModel) {
            foreach ($approvedChanges as $key => $value) {
                if (isset($existingModel[$key])) {
                    $existingModel[$key] = $value;
                }
            }
        } else {
            foreach ($approvedChanges as $key => $value) {
                if (isset($existingModel[$subModel][$key])) {
                    $existingModel[$subModel][$key] = $value;
                }
            }
        }
        return $existingModel;
    }

    /**
     * @param        $passIn
     * @param bool   $givenId
     * @param bool   $subModel
     * @param string $crudOperation
     *
     * @return array
     * @throws Exception
     */
    private static function prepareForTransaction(
        $passIn,
        $givenId = false,
        $subModel = false,
        $crudOperation = 'create'
    ) {
        $structure = self::$transformer::modelStructure($givenId);
        $sanitized = [];
        switch ($crudOperation) {
            case 'create':
                $sanitized = TransformValidator::validateStructureCreate($passIn, $structure, $subModel);
                break;
            case 'update':
            case 'delete':
                $sanitized = TransformValidator::validateStructureUpdateOrDelete($passIn, $structure, $subModel,
                    $crudOperation);
                break;
        }

        return TransformValidator::flatten(self::$model, $sanitized);
    }

    /**
     * @param            $preparedTransactions
     * @param null|array $updateCondition
     *
     * @throws DbException
     */
    private static function executeTransactions($preparedTransactions, $updateCondition = null)
    {
        foreach ($preparedTransactions as $table => $values) {
            reset($values);
            if (key($values) === 0) {
                foreach ($values as $valueSet) {
                    if (!empty($valueSet)) {
                        Db::ask($table, $valueSet, $updateCondition);
                    }
                }
            } else {
                if (!empty($values)) {
                    Db::ask($table, $values, $updateCondition);
                }
            }
        }
    }

    /**
     * @param $idString
     *
     * @return string|string[]|null
     */
    private static function sanitizeId($idString)
    {
        if (is_numeric($idString)) {
            return $idString;
        } else {
            return preg_replace('/\$|UNHEX|\(|\)/', '', $idString);
        }
    }
}
