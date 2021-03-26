<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TransformInput
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next,$transformer)
    {
        $transformedInput = [];

        // Don't want to share certain column names as they are clunky and also reveal information about the db structure which could be exploited.
        // This middleware uses a transformer to change column names back to their original value
        foreach ($request->request->all() as $input => $value) {
            $transformedInput[$transformer::originalAttribute($input)] = $value;
        }

        $request->replace($transformedInput);

        $response = $next($request);

        // If there is a validation exception, the column names for which the error is shown is turned back to the outside value.
        if(isset($response->exception) && $response->exception instanceof ValidationException){
            $data = $response->getData();

            $transformedErrors = [];

            foreach ($data->error as $field => $errorMessage) {
                $transformedField = $transformer::transformedAttribute($field);

                if(strpos($field,'_') !== false){
                    $field = str_replace('_',' ',$field);
                }

                $transformedErrors[$transformedField] = str_replace($field,$transformedField,$errorMessage);
            }

            $data->error = $transformedErrors;

            $response->setData($data);
        }

        return $response;
    }
}
