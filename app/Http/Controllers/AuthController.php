<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Validator;

class AuthController extends Controller
{
    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $credentials = $request->only(['email', 'password']);
        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->respondWithToken($token);
    }

    // @webipe@

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return  auth('api')->user();
    }


    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        if (!auth('api')) {
            return response()->json(['response' => '']);
        }
        auth('api')->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth('api')->refresh());
    }

    public function recoverPassword(Request $request)
    {
        return \App\User::recoverPassword($request->all());
    }
    public function recoverPasswordAfterEmail(Request $request)
    {

        $url = $request['url'];
        $token = $request['token'];


        $criptDecode = json_decode(base64_decode($url));
        $created_at = base64_decode($token);

        $funcionario = \App\Funcionario::where('email', $criptDecode->email)->where('id', $criptDecode->id)->first();

        if($funcionario->created_at != $created_at){
            return response(['response' => 'Error'], 400);
        }


        $expire = strtotime($criptDecode->expired);
        $now = strtotime(date("Y-m-d H:i:s"));

        if($now >= $expire){
            return response(['response' => 'Link expirado'], 400);
        }

        try {
            return $this->changePassword($funcionario, $request->all());
        } catch (\Throwable $th) {
            return response(['response' => $th->getMessage()], 400);
        }
    }

    public function changePassword($user,$dados)
    {
        if ($dados['newPassword'] != $dados['confirmPassword']) {
            return response(['response' => 'As senhas não conferem'], 400);
        }

        if (in_array($dados['newPassword'], \App\User::getWorstPassword())) {
            return response(['response' => 'A senha informada é muito fraca'], 400);
        }

        try {
            //code...
            \App\User::changePassword($dados, $user->id);

            $credentials['email'] = $user->email;
            $credentials['password'] = $dados['newPassword'];

            if (!$token = auth('api')->attempt($credentials)) {
                throw new \Exception("Unauthorized");
            }

            return $this->respondWithToken($token);

        } catch (\Throwable $th) {
            return response(['response' => $th->getMessage()], 400);
        }
    }

    /**
     * Get the token array structure.
     *
     * @param string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        $me = $this->me();

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'me' => $me,
        ]);
    }
}
