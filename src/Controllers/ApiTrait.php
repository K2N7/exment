<?php

namespace Exceedone\Exment\Controllers;

use Illuminate\Http\Request;
use Exceedone\Exment\Enums\ErrorCode;
use Exceedone\Exment\Model\CustomTable;

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
        $maxcount = config('exment.api_max_data_count', 100);
        if (!preg_match('/^[0-9]+$/', $count) || intval($count) < 1 || intval($count) > $maxcount) {
            return abortJson(400, exmtrans('api.errors.over_maxcount', $maxcount), ErrorCode::INVALID_PARAMS());
        }

        return $count;
    }
    
    /**
     * get join table name list from querystring
     * @param Request $request
     * @param string $prefix
     */
    protected function getJoinTables(Request $request, $prefix)
    {
        $join_tables = [];
        if ($request->has('expands')) {
            $join_tables = collect(explode(',', $request->get('expands')))
                ->map(function ($expand) use ($prefix) {
                    $expand = trim($expand);
                    switch ($expand) {
                        case 'tables':
                        case 'statuses':
                        case 'action':
                        case 'actions':
                        case 'columns':
                        case 'status_from':
                        case 'status_to':
                            return $prefix . '_' . $expand;
                    }
                })->filter()->toArray();
        }
        return $join_tables;
    }

    /**
     * Get Custom Value (or return response)
     *
     * @param CustomTable $custom_table
     * @param [type] $id
     * @return void
     */
    protected function getCustomValue(CustomTable $custom_table, $id)
    {
        $custom_value = getModelName($custom_table->table_name)::find($id);
        // not contains data, return empty data.
        if (!isset($custom_value)) {
            $code = $custom_table->getNoDataErrorCode($id);
            if ($code == ErrorCode::PERMISSION_DENY) {
                return abortJson(403, $code);
            } else {
                // nodata
                return abortJson(400, $code);
            }
        }

        return $custom_value;
    }
}
