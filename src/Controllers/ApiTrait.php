<?php

namespace Exceedone\Exment\Controllers;

use Illuminate\Http\Request;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomView;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\NotifyNavbar;
use Exceedone\Exment\Enums\Permission;
use Exceedone\Exment\Enums\ColumnType;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Enums\ViewKindType;
use Exceedone\Exment\Enums\ErrorCode;

/**
 * Api about target table
 */
trait ApiTrait
{
    /**
     * Get error message from validator
     *
     * @param [type] $validator
     * @return array error messages
     */
    protected function getErrorMessages($validator)
    {
        $errors = [];
        foreach ($validator->errors()->messages() as $key => $message) {
            if (is_array($message)) {
                $errors[$key] = $message[0];
            } else {
                $errors[$key] = $message;
            }
        }
        return $errors;
    }

    /**
     * Get count parameter for list count
     *
     * @param [type] $request
     * @return void
     */
    protected function getCount($request)
    {
        // get and check query parameter
        
        if (!$request->has('count')) {
            return config('exment.api_default_data_count', 20);
        }

        $count = $request->get('count');
        if (!preg_match('/^[0-9]+$/', $count) || intval($count) < 1 || intval($count) > 100) {
            return abortJson(400, exmtrans('api.errors.over_maxcount'));
        }

        return $count;
    }
    
}