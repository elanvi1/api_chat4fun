<?php

namespace App\Exceptions;

use Throwable;
use App\Traits\ApiResponser;
use Illuminate\Database\QueryException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException as ModelNotFoundException;

class Handler extends ExceptionHandler
{
    use ApiResponser;
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    public function render($request, Throwable $e)
    {
        if ($e instanceof ModelNotFoundException) {
            $modelName = strtolower(class_basename($e->getModel()));

            if($modelName === 'groupuser'){
                return $this->errorResponse("User is not in the specified group",404);
            }

            if($modelName === 'roleuser'){
                return $this->errorResponse("User doesn't have any role", 404);
            }

            return $this->errorResponse("There is no {$modelName} with the specified identificator", 404);
        }

        if($e instanceof ValidationException){
            return $this->convertValidationExceptionToResponse($e, $request);
        }

        if ($e instanceof QueryException) {
            $errorCode = $e->errorInfo[1];
           
            if($errorCode == 1451){
                return $this->errorResponse('Cannot remove this resource permanetly. It is related with another resource. Foreign key constraing, DB error code '.$errorCode, 409);
            }

            // \Log::debug($e->errorInfo);

            return $this->errorResponse("Error ".$errorCode." was given while performing a query on the DB", 409);
        }

        if($e instanceof AuthenticationException){
            $this->unauthenticated($request,$e);
        }

        if ($e instanceof AuthorizationException) {
            return $this->errorResponse($e->getMessage(),401);
        }

        if ($e instanceof RouteNotFoundException) {
            return $this->errorResponse('Please log in so that you can access this endpoint',401);
        }

        if ($e instanceof NotFoundHttpException) {
            return $this->errorResponse('The specified URL cannot be found',404);
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return $this->errorResponse('Method not allowed at this endpoint',405);
        }

        if ($e instanceof HttpException) {
            // dd($e);
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        }

        if ($e instanceof TokenMismatchException) {
            return $this->errorResponse($e->getMessage(), 401);
        }

        if ($e instanceof ThrottleRequestsException) {
            return $this->errorResponse($e->getMessage(), 429);
        }

        // if(config('app.debug')){
        //     return parent::render($request, $e);
        // }

        //  dd($e->getMessage());

        dd($e);

        return $this->errorResponse('Unexpected Exception. Try again later', 500);
    }

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
       
    }

    protected function convertValidationExceptionToResponse(ValidationException $e, $request)
    {
        $errors = $e->validator->errors()->getMessages();

        return $this->errorResponse($errors, 422);
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return $this->errorResponse('Unauthenticated.', 401);
    }
}
