<?php

namespace App\Http\Controllers;

use App\Models\Amenity;
use App\Models\Country;
use App\Models\Facility;
use App\Models\Policy;
use App\Models\PropertyType;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class DefaultValuesController extends Controller
{
   public function GetDefaultList(Request $request)
   {
      switch ($request->item) {
         case 'amenities' :
            $responseData = Amenity::all(['id','name','icon_class']);
            break;
         case 'facilities' :
            $responseData = Facility::all(['id','name','icon_class']);
            break;
         case 'policies' :
            $responseData = Policy::with('sub_policies')->get(['id','name']);
            break;
         case 'property_types' :
            $responseData = PropertyType::all(['id','name','description']);
            break;
         case 'currency' :
            $responseData = Country::all(['id','currency']);
            break;
         default :
            $responseData['options'] = ['amenities', 'facilities', 'policies', 'property_types', 'currency'];
            break;
      }

      return ApiResponse::returnData($responseData);
   }
}
