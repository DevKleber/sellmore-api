<?php

namespace App;

use App\Mail\SendMailRecover;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Support\Facades\Mail;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;
    protected $table = 'usuario';
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['nome', 'email', 'usuario', 'password', 'bo_ativo'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // Rest omitted for brevity

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public static function recoverPassword($request)
    {
        $linkFront = "https://wiseller.com.br/request-recover-password";
        $funcionario = \App\Funcionario::getEmployeeByEmail($request['email']);
        if (!$funcionario) {
            return response(['response' => 'Não encontrado'], 400);
        }

        $link = [
            "email"=> $funcionario->email,
            "id"=> "1",
            "expired" => now()->addMinutes(1440)
        ];
        $cript = base64_encode(json_encode($link));
        $token = base64_encode($funcionario->created_at);

        Mail::to($funcionario->email)->send(new SendMailRecover([
            'no_pessoa' => $funcionario->nome,
            'link' => "{$linkFront}?url=".$cript."&token={$token}"
        ]));

        return response(['response' => 'Enviamos um e-mail com o link para alteração de senha.']);
    }

    public static function changePassword($request, $id_pessoa)
    {
        $funcionario = \App\Funcionario::find($id_pessoa);

        $funcionario->password = \Hash::make(($request['newPassword']));

        if (!$funcionario->update()) {
            throw new \Exception("Erro ao alterar");
        }

        return $funcionario->password;
    }

    public static function getWorstPassword()
    {
        return [
            '123abc',
            '123',
            '1234',
            '12345',
            '123456',
            '1234567',
            '12345678',
            '123456789',
            '1234567890',
            'qwerty',
            'password',
            '111111',
            'abc123',
            'password1',
            '123123',
            '000000',
            'iloveyou',
            '1q2w3e4r5t',
            'qwertyuiop',
            'monkey',
            'dragon',
        ];
    }
}
