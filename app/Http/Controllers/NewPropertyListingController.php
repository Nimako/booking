<?php

namespace App\Http\Controllers;

use App\Models\Amenity;
use App\Models\CommonPropertyFacility;
use App\Models\CommonPropertyPolicy;
use App\Models\CommonRoomAmenities;
use App\Models\Facility;
use App\Models\Policy;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\RoomApartment;
use App\Models\RoomDetails;
use App\Models\SubPolicy;
use App\Traits\ApiResponse;
use App\Traits\ImageProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Uuid;

class NewPropertyListingController extends Controller
{
   public function onBoarding(Request $request)
   {
      // if property exists
      if(!empty($request->id))
      {
         $stringGlue = "**";
         // validation
         $rules = [
            'id' => "required|exists:properties,uuid",
            'current_onboard_stage' => "required",
            'created_by' => "required"
         ];
         $validator = Validator::make($request->all(), $rules, $customMessage = ['id.exists' => "Invalid Property Reference"]);
         if($validator->fails()) {
            return ApiResponse::returnErrorMessage($message = $validator->errors());
         }
         else {
            // if property record found
            $searchedProperty = Property::where(['uuid' => $request->id])->first();

            // property data pre-processing
            if(!empty($request->latitude) || !empty($request->longitude))
               $request->request->add(['geolocation' => $request->latitude.','.$request->longitude]);
            if(!empty($request->languages_spoke))
               $request->request->add(['languages_spoken' => implode($stringGlue, (array)$request->languages_spoke)]);
            if(!empty($request->property_type_id))
               $request->merge(['property_type_id' => PropertyType::where(['uuid' => $request->property_type_id])->first()->id]);
            if(!empty($request->room_details))
               $request->request->add(['num_of_rooms' => sizeof($request->room_details)]);

            $request->request->add(['property_id' => $searchedProperty->id]);

            // saving property info
            $propertyUpdateResponse = Property::find($searchedProperty->id)->update($request->all());

            # if facilities added to request
            if(!empty($request->facilities)) {
               $searchedFacilities = Facility::wherein('id', (array)$request->facilities)->get(['name'])->toArray();
               if($propertyCommonFacilities = CommonPropertyFacility::where(['property_id' => $searchedProperty->id])->first())
                  $doNothing = "";
               else {
                  $propertyCommonFacilities = new CommonPropertyFacility();
                  $propertyCommonFacilities->property_id = $searchedProperty->id;
               }

               // saving data
               $facilitiesByName = array_map(function($facility) { return $facility['name']; }, $searchedFacilities);
               $propertyCommonFacilities->facility_ids = trim(implode($stringGlue, (array)$request->facilities), $stringGlue);
               $propertyCommonFacilities->facility_text = trim(implode($stringGlue, (array)$facilitiesByName), $stringGlue);
               $propertyCommonFacilities->save();
            }

            # if policies added to request
            if(!empty($request->subpolicies))
            {
               $subPolicyText = $subPolicyIds = "";
               foreach ($request->subpolicies as $key => $value) {
                  if($subPolicy = SubPolicy::find($key)) {
                     $subPolicyIds .= $key.$stringGlue;
                     $subPolicyText .= $subPolicy->name.'='.$value.$stringGlue;
                  }
               }

               if($propertyCommonPolicies = CommonPropertyPolicy::where(['property_id' => $searchedProperty->id])->first())
                  $doNothing = "";
               else {
                  $propertyCommonPolicies = new CommonPropertyPolicy();
                  $propertyCommonPolicies->property_id = $searchedProperty->id;
               }

               // saving data
               $propertyCommonPolicies->sub_policy_ids = trim($subPolicyIds, $stringGlue);
               $propertyCommonPolicies->sub_policy_text = trim($subPolicyText, $stringGlue);
               $propertyCommonPolicies->save();
            }

            // apartment details
            if(!empty($request->room_size) || !empty($request->total_guest_capacity) || !empty($request->total_rooms) || !empty($request->total_bathrooms))
            {
               if($room = RoomApartment::where(['property_id' => $searchedProperty->id])->first())
                  $room->update($request->all());
               else
                  $room = RoomApartment::create($request->all());

               if(!empty($request->room_details))
               {
                  foreach ($request->room_details as $detail) {
                     $bedDetails[] = [
                        'room_id' => $room->id,
                        'room_name' => $detail['name'],
                        'bed_type' => $detail['bed_type'],
                        'bed_type_qty' => $detail['bed_qty']
                     ];
                  }
                  RoomDetails::insert($bedDetails);
               }
            }

            # if amenities added to request
            if(!empty($request->amenities)) {
               $searchedAmenities = Amenity::wherein('id', (array)$request->amenities)->get(['name'])->toArray();
               if($commonAmenities = CommonRoomAmenities::where(['room_id' => $room->id])->first())
                  $doNothing = "";
               else {
                  $commonAmenities = new CommonRoomAmenities();
                  $commonAmenities->room_id = $room->id;
               }

               // saving data
               $amenitiesByName = array_map(function($amenity) { return $amenity['name']; }, $searchedAmenities);
               $commonAmenities->popular_amenity_ids = trim(implode($stringGlue, (array)$request->amenities), $stringGlue);
               $commonAmenities->popular_amenity_text = trim(implode($stringGlue, (array)$amenitiesByName), $stringGlue);
               $commonAmenities->save();

               // updating link to room details
               $searchedRoom = RoomApartment::find($room->id);
               $searchedRoom->common_room_amenity_id = $commonAmenities->id;
               $searchedRoom->save();
            }

            # image uploads
            if($request->hasFile('images')) {
               foreach ($request->file('images') as $image){
                  $fileStoragePaths[] =  ImageProcessor::UploadImage($image, $request->id);
               }
               # searching for record
               if($room = RoomApartment::where(['property_id' => $searchedProperty->id])->first()) {
                  // unlinking previous files
                  if(!empty($room->image_paths)) {
                     foreach ($filePaths = explode($stringGlue, $room->image_paths) as $filePath) {
                        unlink('storage/'.$filePath);
                     }
                  }
                  # updating file upload field
                  $room->update(['image_paths' => implode($stringGlue, $fileStoragePaths)]);
               }
            }

            // return statement
            return ApiResponse::returnSuccessData($data = ['id' => $searchedProperty->uuid, 'completed_onboard_stage' => $request->current_onboard_stage]);
         }
      }
      // new property
      else {
         // validation
         $rules = [
            'property_type_id' => "required|exists:property_types,uuid",
         ];
         $validator = Validator::make($request->all(), $rules, $customMessage = ['property_type_id.exists' => "Invalid Property Type Reference"]);
         if($validator->fails())
            return ApiResponse::returnErrorMessage($message = $validator->errors());

         // data pre-processing
         $request->request->add(['uuid' => Uuid::uuid6()]);
         if(!empty($request->property_type_id))
            $request->merge(['property_type_id' => PropertyType::where(['uuid' => $request->property_type_id])->first()->id]);

         // saving data
         $responseData = Property::create($request->all());
         // return statement
         return ApiResponse::returnSuccessData(array('id' => $responseData->uuid, 'completed_onboard_stage' => "Stage1"));
      }
   }

   public function onBoardingDetails(Request $request)
   {
      // Validation
      $rules = [
         'id' => "required|exists:properties,uuid",
         'current_onboard_stage' => "required"
      ];
      $validator = Validator::make($request->all(), $rules, $customMessage = ['id.exists' => "Invalid Property Reference"]);
      if($validator->fails()) {
         return ApiResponse::returnErrorMessage($message = $validator->errors());
      }
      else{
         // if property record found
         $searchedProperty = Property::where(['uuid' => $request->id])->first();

         // return statement
         return ApiResponse::returnSuccessMessage($message = "Stage 6 Completed");
      }
   }
}
