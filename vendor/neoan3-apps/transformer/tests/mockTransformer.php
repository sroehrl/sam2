<?php

use Neoan3\Apps\Db;
use Neoan3\Apps\DbException;
use Neoan3\Model\IndexTransformer as IndexTransformerAlias;

require_once '../vendor/neoan3-model/index/Index.transformer.php';
require_once '../vendor/autoload.php';

/**
 * Class MockTransformer
 */
class MockTransformer implements IndexTransformerAlias
{

    /**
     * @param $input
     * @param $all
     *
     * @return mixed
     * @throws DbException
     * @throws Exception
     */
    private static function checkUniqueUserName($input, $all){
        $u = isset($all['userName']) ? Db::easy('user.id',['user_name'=>$all['userName']]) : [];
        if(empty($u)){
            return $input;
        } else {
            throw new Exception('userName not unique');
        }
    }

    /**
     * @param $input
     * @param $all
     *
     * @return mixed
     * @throws DbException
     * @throws Exception
     */
    private static function checkUniqueEmail($input, $all){
        $e = isset($all['email']['email']) ? Db::easy('user_email.id',['email'=>$all['email']['email'],'^delete_date']) : [];
        if(empty($e)){
            return $input;
        } else {
            throw new Exception( 'Email not unique');
        }
    }

    /**
     * @param bool $additionalInput
     *
     * @return array
     * @throws DbException
     */
    static function modelStructure($additionalInput = false){
        $mainId = $additionalInput ? $additionalInput : Db::uuid()->uuid;
        return [
            'id' => [
                'on_creation' => function($input) use ($mainId){
                    $mainId = $input ? $input : $mainId;
                    return '$'. $mainId;
                }
            ],
            'inserted'=>[
                'translate' => 'insert_date',
                'on_read' => function($input){ return '#user.'.$input;},
                'on_creation' => function($input,$all){
                    self::checkUniqueEmail($input, $all);
                    self::checkUniqueUserName($input, $all);
                    return $input;
                }
            ],
            'userName'=>[
                'required'=>true,
                'translate' => 'user_name',
                'on_update' => function($input,$all){return self::checkUniqueUserName($input, $all);}
            ],
            'delete_date' => [
                'on_delete' => function($void, $all){
                    $all['email']['delete_date'] = '.';
                    return '.';
                }
            ],
            'email' => [
                'translate' =>'user_email',
                'required' => true,
                'depth' => 'one',
                'required_fields' => ['email'],
                'on_creation' =>[
                    'confirm_code' => function($input,$all){
                        self::checkUniqueEmail('',$all);
                        return 'Ops::hash(23)';
                    },
                    'user_id' => function() use ($mainId){ return '$' . $mainId;}
                ],
                'on_read' =>[
                    'insert_date' =>function($input){ return '#user_email.'.$input.':inserted';}
                ]
            ],
            'password' => [
                'translate' =>'user_password',
                'protection' =>'hidden',
                'required' => true,
                'required_fields' => ['password'],
                'depth' => 'one',
                'on_creation' => [
                    'password' => function($input){
                        return '=' . password_hash($input, PASSWORD_DEFAULT);
                    },
                    'confirm_code' => function(){return 'somehash';},
                    'user_id' => function() use ($mainId){ return '$' . $mainId;}
                ]
            ]
        ];
    }

}
