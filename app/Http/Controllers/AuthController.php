<?php

namespace App\Http\Controllers;

use App\Domain\Customer\ValueObjects\Document;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        error_log('Entrou aqui');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'document' => 'required|string|max:14|unique:users,document',
            'email' => 'required|string|email|max:255',
            'document' => 'required|string|max:14,document',
            'password' => 'required|confirmed|min:8',
        ]);


        if($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }


        error_log('validou');

        $document = new Document((string) request('document'));

        $user = new User();
        $user->name = request('name');
        $user->email = request('email');
        $user->document = $document->getValue();
        $user->password = bcrypt(request('password'));

        error_log('salvando');
        $user->save();

        error_log('salvo');

        unset($user->password);

        return response()->json(['message' => 'Novo usuário criado com sucesso', 'user' => $user], 201);
    }

    public function login()
    {
        $credentials = request(['email', 'password']);
        /** @var \Tymon\JWTAuth\JWTGuard $auth */
        $auth = auth();

        if (!$token = $auth->attempt($credentials)) {
            return response()->json(['message' => 'Credenciais inválidas'], 401);
        }

        return response()->json([
             'access_token' => $token,
             'token_type' => 'bearer',
        ]);
    }

    public function me()
    {
        /** @var \Tymon\JWTAuth\JWTGuard $auth */
        $auth = auth();
        return response()->json($auth->user());
    }

    public function logout()
    {
        /** @var \Tymon\JWTAuth\JWTGuard $auth */
        $auth = auth();
        $auth->logout();

        return response()->json(['message' => 'Usuário deslogado com sucesso']);
    }

    public function refresh()
    {
        /** @var \Tymon\JWTAuth\JWTGuard $auth */
        $auth = auth();
        return $this->respondWithToken($auth->refresh());
    }

    protected function respondWithToken($token)
    {
        /** @var \Tymon\JWTAuth\JWTGuard $auth */
        $auth = auth();
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $auth->factory()->getTTL() * 60
        ]);
    }
}
