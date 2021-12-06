<?php

namespace App\Http\Controllers;

use App\Http\Resources\User as UserResources;
use App\Http\Resources\UsersCollection;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return new UsersCollection(User::paginate());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function show(User $user)
    {
        return (new UserResources($user))->additional([
            'meta' => [
                'message'   => "User data ".$user->name,
                'code'      => Response::HTTP_OK,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user)
    {
        $requestValidate = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
        ]);

        if ($requestValidate->fails()) {
            return $this->responseJson("invalid request", Response::HTTP_BAD_REQUEST, $requestValidate->errors()->toJson());
        }

        $user->update(
            $requestValidate->validated()
        );

        return $this->responseJson("Successfully Edit Profile", Response::HTTP_OK, $user);
    }

    public function updatePassword(Request $request, User $user)
    {
        $requestValidate = Validator::make($request->all(), [
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($requestValidate->fails()) {
            return $this->responseJson("invalid request", Response::HTTP_BAD_REQUEST, $requestValidate->errors()->toJson());
        }

        $user->update(['password' => Hash::make($requestValidate->validated()['password'])]);

        return $this->responseJson("Successfully Edit Password", Response::HTTP_OK);
    }

    public function updatePhoto(Request $request, User $user)
    {
        $requestValidate = Validator::make($request->all(), [
            'photo' => 'required|file|max:2520',
        ]);

        if ($requestValidate->fails()) {
            return $this->responseJson("invalid request", Response::HTTP_BAD_REQUEST, $requestValidate->errors()->toJson());
        }

        try {
            if ($request->hasFile('photo')) {

                if (file_exists(public_path('photo')."/".$user->photo)) {
                    File::delete(public_path('photo')."/".$user->photo);
                }

                $file = $request->file('photo');
                $namePhoto = time()."_".str_replace(' ',"_", $file->getClientOriginalName());

                $file->move(public_path('photo'), $namePhoto);
            }

            $user->update(
                [
                    'photo' =>  $namePhoto,
                ]
            );

        } catch (\Exception $e) {
            return $this->responseJson("Internal Server Error", Response::HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
        }

        return $this->responseJson("Successfully Edit Photo Profile", Response::HTTP_OK, $user);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        //
    }
}
