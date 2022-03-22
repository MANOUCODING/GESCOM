<?php

namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{

    public function login(Request $request)
    {
        if (filter_var($request->input('username'), FILTER_VALIDATE_EMAIL)) {
            $login_type = 'username';
        } else {
            $login_type = 'phone';
        }
        if ($login_type === 'username') {
            $validator = Validator::make($request->all(), [
                'username' => 'required|email',
                'password' => 'required|string|min:5',
            ], [
                'username.required' => 'Le :attribute ou numero de téléphone est obligatoire.',
                'password.required' => 'Le :attribute est obligatoire.'
            ],[
                'username' => 'L\'email',
                'password' => 'mot de passe'
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $username = $request->input('username');
            $currentUser = User::where('email', $username)->where('status', true)->first();;

            if (is_null($currentUser)) {
                return response()->json(['error' => true,
                    'message' => 'Aucun utilisateur trouvé avec cet email.'], 401);
            }

            if ($currentUser->status === 0) {
                return response()->json(['error' => true,
                    'activate' => false,
                    'message' => 'Veuillez activer votre compte.'], 401);
            }

            $credentials = array_merge(['email' => $request->input('username')], $request->only('password'));
            if (!$token = Auth::guard('api')->attempt($credentials)) {
                return response()->json([
                    'error' => true,
                    'errorType' => 'credentialsError',
                    'message' => 'Les identifiants fournis sont invalides(pas autoris(é)e).'], 401);
            }
        }
        //Phone / Password Login
        if ($login_type === 'phone') {
            $validator = Validator::make($request->all(), [
                //'telephone_1' => 'required|regex:/(228)[0-9]{8}/',
                'username' => 'required|regex:/[0-9]/',
                'password' => 'required|string|min:5',
            ], [
                'required' => 'Le :attribute ou email est obligatoire.',
                'password.required' => 'Le :attribute est obligatoire.'
            ], [
                'username' => 'numéro de téléphone',
                'password' => 'mot de passe'
            ]);
            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $user = User::where('telephone', $request->username)->first();

            if (is_null($user)) {
                return response()->json(['error' => true, 'message' => 'Aucun utilisateur trouvé avec ce numéro.'], 401);
            }

            if ($user->status === 0) {
                return response()->json(['error' => true,
                    'errorType' => 'activationError',
                    'message' => 'Veuillez activer votre compte.'], 401);
            }

            if ($user->status === 2) {
                return response()->json(['error' => true,
                    'errorType' => 'suspended',
                    'message' => 'Votre compte est suspendu.'], 401);
            }

            $credentials = array_merge(['telephone_1' => $request->input('username')], $request->only('password'));
            if (!$token = Auth::guard('api')->attempt($credentials)) {
                return response()->json(['error' => true,
                    'errorType' => 'credentialsError',
                    'message' => 'Les identifiants fournis sont invalides.'], 401);
            }
        }
        $_SESSION['token'] = $token;
        return $this->createNewToken($token);
    }

    public function me()
    {
        $user = User::with('roles', 'agents')
            ->where('id', auth()->user()->id)
            ->firstOrFail();
        return response()->json([
            'status' => 'success',
            'data' => $user
        ]);
    }

    public function logout()
    {
        auth()->logout();
        return response()->json(['message' => 'Déconnexion reussie.']);
    }

    protected function createNewToken(string $token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->guard('api')->factory()->getTTL() * 60
        ])->header('Authorization', $token);
    }
}
