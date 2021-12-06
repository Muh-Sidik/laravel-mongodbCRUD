<?php

namespace App\Http\Controllers;

use App\Http\Resources\User as UserResources;
use Illuminate\Http\Request;
use App\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $requestValidate = Validator::make($request->all(),[
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($requestValidate->fails()) {
            return $this->responseJson("invalid request", Response::HTTP_BAD_REQUEST, $requestValidate->errors()->toJson());
        }

        try {
            if (!$token = Auth::attempt($requestValidate->validated())) {
                return $this->responseJson("invalid credential", Response::HTTP_BAD_REQUEST);;
            }
        } catch (JWTException $e) {
            report($e);
            return $this->responseJson("could not create token", Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->responseJson("Successfully Logged", Response::HTTP_OK, $this->respondWithToken($token, Auth::user()));
    }

    public function register(Request $request)
    {
        $requestValidate = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'photo' => 'required|file|max:2520',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($requestValidate->fails()) {
            return $this->responseJson("invalid request", Response::HTTP_BAD_REQUEST, $requestValidate->errors()->toJson());
        }

        try {
            if ($request->hasFile('photo')) {

                if (!file_exists(public_path('photo'))) {
                    File::makeDirectory(public_path('photo'), 0755, true, true);
                }

                $file = $request->file('photo');
                $namePhoto = time()."_".str_replace(' ',"_",$file->getClientOriginalName());

                $file->move(public_path('photo'), $namePhoto);
            }

            $user = User::create(
                array_merge($requestValidate->validated(),
                [
                    'password' => Hash::make($request->input('password')),
                    'photo' =>  $namePhoto,
                ])
            );

        } catch (\Exception $e) {
            return $this->responseJson("Internal Server Error", Response::HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
        }


        $token = JWTAuth::fromUser($user);

        return $this->responseJson("Successfully Registered", Response::HTTP_CREATED, $this->respondWithToken($token, $user));
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile()
    {
        try {

            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }

        } catch (TokenExpiredException $e) {

            return response()->json(['token_expired'], $e->getStatusCode());

        } catch (TokenInvalidException $e) {

            return response()->json(['token_invalid'], $e->getStatusCode());

        } catch (JWTException $e) {

            return response()->json(['token_absent'], $e->getStatusCode());

        }

        return (new UserResources($user))->additional([
            'meta' => [
                'message'   => "User data",
                'code'      => Response::HTTP_OK,
            ],
        ]);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        Auth::logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(Auth::refresh(), Auth::user());
    }
}
