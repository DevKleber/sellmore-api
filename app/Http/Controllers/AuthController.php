<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Validator;

class AuthController extends Controller
{
    private const TAMANHO_MINIMO_DA_SENHA = 6;
    private const LINK_INVALIDO = 'Link inválido ou já utilizado. Peça um novo.';

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
        $funcionario = $this->usuarioDoLinkDeRecuperacao((string) $request['url'], (string) $request['token']);
        if (!$funcionario) {
            return response(['response' => self::LINK_INVALIDO], 400);
        }

        $conteudo = json_decode(base64_decode($request['url']));
        if (now()->timestamp >= strtotime($conteudo->expired ?? '')) {
            return response(['response' => 'Link expirado'], 400);
        }

        return $this->changePassword($funcionario, $request->all());
    }

    /**
     * So devolve o usuario quando o link foi assinado por esta API para ele.
     */
    private function usuarioDoLinkDeRecuperacao(string $url, string $assinatura)
    {
        $conteudo = json_decode(base64_decode($url));
        if (!isset($conteudo->email, $conteudo->id)) {
            return null;
        }

        $funcionario = \App\Funcionario::where('email', $conteudo->email)->where('id', $conteudo->id)->first();
        if (!$funcionario) {
            return null;
        }

        $assinaturaEsperada = \App\User::assinaturaDoLinkDeRecuperacao($url, $funcionario);

        return hash_equals($assinaturaEsperada, $assinatura) ? $funcionario : null;
    }

    public function trocarSenhaLogado(Request $request)
    {
        $usuario = auth('api')->user();
        if (!\Hash::check((string) $request['currentPassword'], $usuario->password)) {
            return response(['response' => 'A senha atual não confere'], 400);
        }

        return $this->changePassword($usuario, $request->all());
    }

    private function changePassword($user,$dados)
    {
        $dados += ['newPassword' => '', 'confirmPassword' => ''];
        if ($dados['newPassword'] != $dados['confirmPassword']) {
            return response(['response' => 'As senhas não conferem'], 400);
        }

        $senhaCurtaDemais = strlen((string) $dados['newPassword']) < self::TAMANHO_MINIMO_DA_SENHA;
        if ($senhaCurtaDemais || in_array($dados['newPassword'], \App\User::getWorstPassword())) {
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
