<?php

namespace App\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;

// Creating a trait which will be used in the ApiController which will be extended by every controller. This is done in order to not repeat myself since all responses will have something in common with others.

trait ApiResponser{
  
  private function successResponse($data, $code,$info){
    return response()->json(['data'=>$data,'info'=>$info],$code);
  }

  protected function errorResponse($message,$code,$info = null){
    return response()->json(['error' => $message,'info'=>$info,'code'=>$code],$code);
  }

  protected function showInfo($data, $code = 200, $info = null){

    $this->cacheResponse($data);

    return $this->successResponse($data,$code,$info);
  }

  protected function showMessage($message, $code = 200,$info = null){
    return response()->json(['message'=>$message,'info'=>$info],$code);
  }

  protected function cacheResponse($data){
    $url = request()->url();

    return Cache::remember($url,60, function() use ($data){
      return $data;
    });
  }
}