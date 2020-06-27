<?php


namespace Neoan3\Apps;


use Exception;

/**
 * Class TransformValidator
 *
 * @package Neoan3\Apps
 */
class TransformValidator
{
    /**
     * @param $modelName
     * @param $deepModel
     *
     * @return array
     */
    static function flatten($modelName, $deepModel)
    {
        $separate = [];
        foreach ($deepModel as $columnOrTable => $value) {
            if (is_array($value)) {
                $separate[$columnOrTable] = $value;
            } else {
                $separate[$modelName][$columnOrTable] = $value;
            }
        }
        return $separate;
    }

    static function stripUnchangedData($existingEntity, $changedEntity)
    {
        $result = [];
        foreach ($changedEntity as $key => $value) {
            if (!is_array($existingEntity) || !array_key_exists($key, $existingEntity)) {
                $result[$key] = $value;
                continue;
            }
            if (is_array($value)) {
                $recursiveArrayDiff = self::stripUnchangedData($value, $existingEntity[$key]);
                if (count($recursiveArrayDiff)) {
                    $result[$key] = $recursiveArrayDiff;
                }
                continue;
            }
            if ($value != $existingEntity[$key]) {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * @param             $passIn
     * @param             $structure
     * @param bool|string $subModel
     *
     * @param string      $mode
     *
     * @return mixed
     */
    static function validateStructureUpdateOrDelete($passIn, $structure, $subModel = false, $mode = 'update')
    {
        foreach ($passIn as $sub => $any) {
            // clear empty sets
            if (is_array($any) && empty($any)) {
                unset($passIn[$sub]);
            } elseif (is_array($any) && !$subModel) {
                $passIn[$sub] = self::validateStructureUpdateOrDelete($passIn[$sub], $structure[$sub], $sub, $mode);
            }
        }
        foreach ($structure as $tableOrField => $info) {
            if (self::subModelCondition($subModel, $tableOrField)) {
                if (isset($passIn[$tableOrField])) {
                    if (isset($info['on_' . $mode])) {
                        $passIn = self::applyCallback($info, 'on_' . $mode, $tableOrField, $passIn);
                    }
                    $passIn = self::translate($info, $tableOrField, $passIn);
                }
            }
        }
        return $passIn;
    }



    /**
     * @param             $passIn
     * @param             $structure
     * @param bool|string $subModel
     *
     * @return mixed
     * @throws Exception
     */
    static function validateStructureCreate($passIn, $structure, $subModel = false)
    {
        foreach ($structure as $tableOrField => $info) {
            if (self::subModelCondition($subModel, $tableOrField)) {
                // if missing
                if (isset($info['required']) && $info['required'] && !isset($passIn[$tableOrField])) {
                    throw new Exception('Missing: ' . $tableOrField);
                }
                // deep?
                if (isset($info['depth'])) {
                    if(isset($info['required']) && $info['required'] || isset($passIn[$tableOrField])){
                        self::validateRequiredFields($info, $passIn[$tableOrField]);
                    }
                }
                if (isset($info['on_creation'])) {
                    $passIn = self::applyCallback($info, 'on_creation', $tableOrField, $passIn);
                }
                // translate
                $passIn = self::translate($info, $tableOrField, $passIn);
            }

        }
        return $passIn;
    }

    /**
     * @param $subModel
     * @param $column
     *
     * @return bool
     */
    private static function subModelCondition($subModel, $column)
    {
        return !$subModel || $subModel == $column;
    }

    /**
     * @param $currentSub
     * @param $originalName
     * @param $passIn
     *
     * @return mixed
     */
    private static function translate($currentSub, $originalName, $passIn)
    {
        if (isset($currentSub['translate']) && $currentSub['translate'] && isset($passIn[$originalName])) {
            $passIn[$currentSub['translate']] = $passIn[$originalName];
            unset($passIn[$originalName]);
        }
        return $passIn;
    }

    /**
     * @param $currentSub
     * @param $listener
     * @param $outerKey
     * @param $passIn
     *
     * @return mixed
     */
    private static function applyCallback($currentSub, $listener, $outerKey, $passIn)
    {
        $depth = isset($currentSub['depth']) ? $currentSub['depth'] : false;
        if (isset($currentSub[$listener])) {
            switch ($depth) {
                case 'one':
                    foreach ($currentSub[$listener] as $field => $closure) {
                        $value = isset($passIn[$outerKey][$field]) ? $passIn[$outerKey][$field] : false;
                        $passIn[$outerKey][$field] = $closure($value, $passIn);
                    }
                    break;
                case 'many':
                    foreach ($currentSub[$listener] as $field => $closure) {
                        if(isset($passIn[$outerKey])){
                            foreach ($passIn[$outerKey] as $i => $oneInMany) {
                                $value = isset($oneInMany[$field]) ? $oneInMany[$field] : false;
                                $passIn[$outerKey][$i][$field] = $closure($value, $passIn);
                            }
                        }

                    }
                    break;
                default:
                    $passIn[$outerKey] =
                        $currentSub[$listener](isset($passIn[$outerKey]) ? $passIn[$outerKey] : false, $passIn);
                    break;
            }
        }
        return $passIn;
    }

    /**
     * @param $description
     * @param $passIn
     *
     * @throws Exception
     */
    private static function validateRequiredFields($description, $passIn)
    {
        if (isset($description['required_fields'])) {
            $depth = isset($description['depth']) ? $description['depth'] : false;
            foreach ($description['required_fields'] as $field) {
                switch ($depth) {
                    case 'one':
                        if (!isset($passIn[$field])) {
                            throw new Exception('Missing or malformed: ' . $field);
                        }
                        break;
                    case 'many':
                        if (empty($passIn)) {
                            throw new Exception('Missing or malformed: ' . $field);
                        }
                        foreach ($passIn as $oneInMany) {
                            if (!isset($oneInMany[$field])) {
                                throw new Exception('Missing or malformed: ' . $field);
                            }
                        }
                        break;
                }
            }
        }
    }
}
