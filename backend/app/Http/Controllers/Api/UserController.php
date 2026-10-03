<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => User::with('role')->orderBy('name')->get()->map(fn (User $u) => $this->serializar($u)),
            'roles' => Role::pluck('nombre'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);

        $user = User::create([
            'name' => $data['name'],
            'usuario' => $data['usuario'],
            'email' => $data['email'] ?? null,
            'password' => $data['password'],
            'rol_id' => Role::where('nombre', $data['rol'])->value('id'),
            'activo' => true,
        ]);

        Bitacora::registrar('usuario', 'crear', "Creó al usuario \"{$user->usuario}\" ({$user->name}, {$data['rol']}).");

        return response()->json(['user' => $this->serializar($user)], 201);
    }

    /** El Administrador puede cambiar nombre, usuario, cargo, correo y -- si la escribe -- la contraseña. */
    public function update(Request $request, User $user)
    {
        $data = $this->validar($request, $user);

        $user->update([
            'name' => $data['name'],
            'usuario' => $data['usuario'],
            'email' => $data['email'] ?? null,
            'rol_id' => Role::where('nombre', $data['rol'])->value('id'),
            ...(! empty($data['password']) ? ['password' => $data['password']] : []),
        ]);

        $extra = ! empty($data['password']) ? ' y su contraseña' : '';
        Bitacora::registrar('usuario', 'editar', "Editó los datos{$extra} del usuario \"{$user->usuario}\" ({$user->name}).");

        $this->mantenerSesionSiEsElMismo($request, $user);

        return response()->json(['user' => $this->serializar($user)]);
    }

    /**
     * Nombre (para mostrar), usuario (para iniciar sesion), cargo y correo opcional. La contrasena es
     * obligatoria al crear; al editar es opcional (vacia = no se cambia).
     */
    private function validar(Request $request, ?User $user = null): array
    {
        if ($request->filled('usuario')) {
            $request->merge(['usuario' => mb_strtolower(trim($request->input('usuario')), 'UTF-8')]);
        }

        $unico = fn (string $columna) => Rule::unique('users', $columna)->ignore($user?->id)->whereNull('deleted_at');

        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'usuario' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', $unico('usuario')],
            'email' => ['nullable', 'email', 'max:150', $unico('email')],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'rol' => ['required', 'string', 'exists:roles,nombre'],
        ], [
            'name.required' => 'Debes indicar el nombre.',
            'name.max' => 'El nombre no puede superar los 100 caracteres.',
            'usuario.required' => 'Debes indicar el usuario con el que iniciará sesión.',
            'usuario.min' => 'El usuario debe tener al menos 3 caracteres.',
            'usuario.max' => 'El usuario no puede superar los 50 caracteres.',
            'usuario.regex' => 'El usuario solo puede tener letras sin acentos, números, punto, guion y guion bajo (sin espacios).',
            'usuario.unique' => 'Ya existe otro usuario con ese nombre de usuario.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'email.unique' => 'Ya existe un usuario registrado con ese correo.',
            'password.required' => 'Debes indicar una contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'rol.required' => 'Debes seleccionar un cargo.',
            'rol.exists' => 'El cargo seleccionado no es válido.',
        ]);
    }

    public function cambiarPassword(Request $request, User $user)
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ], [
            'password.required' => 'Debes indicar la nueva contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        $user->update(['password' => $data['password']]);

        Bitacora::registrar('usuario', 'editar', "Cambió la contraseña del usuario \"{$user->name}\".");

        $this->mantenerSesionSiEsElMismo($request, $user);

        return response()->json(status: 204);
    }

    public function activar(Request $request, User $user)
    {
        $data = $request->validate([
            'activo' => ['required', 'boolean'],
        ]);

        if (! $data['activo'] && $user->id === $request->user()->id) {
            return response()->json([
                'message' => 'No puedes deshabilitar tu propia cuenta.',
            ], 422);
        }

        $user->update(['activo' => $data['activo']]);

        Bitacora::registrar('usuario', $data['activo'] ? 'activar' : 'desactivar', ($data['activo'] ? 'Habilitó' : 'Deshabilitó')." al usuario \"{$user->name}\".");

        return response()->json(['user' => $this->serializar($user)]);
    }

    /**
     * "Eliminar" un usuario nunca borra la fila -- hace soft delete (queda
     * deleted_at, la fila sigue en la BD para conservar el historial de
     * movimientos y de bitacora). Solo se puede eliminar si ya esta
     * deshabilitado, como salvaguarda extra ante un borrado accidental.
     */
    public function destroy(Request $request, User $user)
    {
        if ($user->activo) {
            return response()->json([
                'message' => 'Solo se pueden eliminar usuarios deshabilitados. Deshabilítalo primero.',
            ], 422);
        }

        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'No puedes eliminar tu propia cuenta.',
            ], 422);
        }

        $nombre = $user->name;
        $user->delete();

        Bitacora::registrar('usuario', 'eliminar', "Eliminó al usuario \"{$nombre}\".");

        return response()->json(status: 204);
    }

    /**
     * Si el Administrador se edita a si mismo, la sesion pasa a usar sus datos nuevos. Sin esto, el
     * middleware AuthenticateSession de Sanctum guarda en la sesion el hash de la contraseña ANTERIOR
     * (la del usuario autenticado en memoria), y en la siguiente peticion lo saca por "Unauthenticated".
     * A los demas usuarios si se les cierra la sesion al cambiarles la contraseña, como debe ser.
     */
    private function mantenerSesionSiEsElMismo(Request $request, User $user): void
    {
        if ($user->is($request->user())) {
            auth()->guard('web')->setUser($user);
        }
    }

    private function serializar(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'usuario' => $user->usuario,
            'email' => $user->email,
            'rol' => $user->role?->nombre,
            'activo' => (bool) $user->activo,
        ];
    }
}
